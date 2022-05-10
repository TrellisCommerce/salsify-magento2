<?php

namespace Trellis\Salsify\Controller\Hook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Trellis\Salsify\Api\PayloadRepositoryInterface;
use Trellis\Salsify\Helper\Data;
use Trellis\Salsify\Logger\Logger;
use Trellis\Salsify\Model\Client;
use Trellis\Salsify\Model\PayloadFactory;
use Trellis\Salsify\Model\Sync\ProductFeed;
use Trellis\Salsify\Model\Sync\ReadinessReport;

abstract class AbstractHook extends Action
{
    const DISABLED = 'disabled';

    const INVALID = 'invalid';

    /**
     * @var
     */
    protected $_pageFactory;

    /** @var ProductFeed */
    protected $_salsifyProductFeedSync;

    /** @var ReadinessReport */
    protected $_salsifyReadinessReportSync;

    /**
     * @var
     */
    protected $_jsonFactory;

    /**
     * @var Data
     */
    protected $_data;

    /**
     * @var Client
     */
    protected $_client;

    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * @var DirectoryList
     */
    protected $_directoryList;

    /**
     * @var DateTime
     */
    protected $_date;

    /**
     * @var PayloadFactory
     */
    protected $_payloadFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var SerializerInterface
     */
    protected $serializer;
    /**
     * @var JsonFactory
     */
    private $_resultJsonFactory;
    /**
     * @var PayloadRepositoryInterface
     */
    protected $payloadRepository;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ProductFeed $productFeedSync
     * @param ReadinessReport $readinessReportSync
     * @param Data $data
     * @param Client $client
     * @param Logger $logger
     * @param DirectoryList $directoryList
     * @param DateTime $date
     * @param StoreManagerInterface $storeManager
     * @param PayloadFactory $payloadFactory
     * @param SerializerInterface $serializer
     * @param PayloadRepositoryInterface $payloadRepository
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProductFeed $productFeedSync,
        ReadinessReport $readinessReportSync,
        Data $data,
        Client $client,
        Logger $logger,
        DirectoryList $directoryList,
        DateTime $date,
        StoreManagerInterface $storeManager,
        PayloadFactory $payloadFactory,
        SerializerInterface $serializer,
        PayloadRepositoryInterface $payloadRepository
    ) {
        parent::__construct($context);
        $this->_salsifyProductFeedSync     = $productFeedSync;
        $this->_salsifyReadinessReportSync = $readinessReportSync;
        $this->_resultJsonFactory          = $resultJsonFactory;
        $this->_data                       = $data;
        $this->_client                     = $client;
        $this->_logger                     = $logger;
        $this->_directoryList              = $directoryList;
        $this->_date                       = $date;
        $this->_payloadFactory             = $payloadFactory;
        $this->_storeManager               = $storeManager;
        $this->serializer = $serializer;
        $this->payloadRepository = $payloadRepository;
    }

    /**
     * @param $payload
     *
     * @return mixed
     */
    abstract public function hook($payload);

    /**
     * @return mixed
     */
    public function execute()
    {
        $payload = $this->getRequest()->getContent();
        $payload = $this->serializer->unserialize($payload);
        $this->_logger->info('REQUEST to WebHook (Action: ' . $this->getRequest()->getFullActionName() .
                             '): ' . ($payload === null ? null : $this->serializer->serialize($payload)));

        $enabled   = $this->_data->getWebhookEnabled();

        if (!$enabled) {
            return $this->returnToSalsify(self::DISABLED);
        }

        // Trigger our sync accordingly:

        $this->hook($payload);
        // End sync logic

        // We're successful, return something reasonable to Salsify:
        $success = [
            'status' => 'success'
        ];

        return $this->_resultJsonFactory->create()->setData($success);
    }

    /**
     * Verify the webhook came from Salsify
     *
     * @param RequestInterface $request
     *
     * @return bool
     */
    protected function verifyWebhook($request)
    {
        // Compare timestamps, don't allow anything older than 5 minutes
        $currentTimestamp = $this->_date->gmtTimestamp();
        $salsifyTimestamp = $request->getHeader('x-salsify-timestamp');
        if ($currentTimestamp - (5 * 60) > $salsifyTimestamp) {
            $this->_logger->info("Request timestamp older than 5 minutes, aborting.");
            return false;
        }

        $certificateUrl      = $request->getHeader('x-salsify-cert-url');
        $certificateUrlParts = parse_url($certificateUrl);

        // Ensure certificate url is  valid
        if ($certificateUrlParts['host'] !== 'webhooks-auth.salsify.com'
            || strtolower($certificateUrlParts['scheme']) !== 'https') {
            $this->_logger->info("Certificate url invalid, aborting.");

            return false;
        }

        // Download the certificate
        $this->_client->get($certificateUrl);
        $certificate = $this->_client->getBody();

        $signature      = $request->getHeader('x-salsify-signature-v1');
        $requestId      = $request->getHeader('x-salsify-request-id');
        $organizationId = $request->getHeader('x-salsify-organization-id');
        $payloadBody    = $request->getContent();

        $webhookUrl     = $this->_url->getCurrentUrl();
        $signatureData = "{$salsifyTimestamp}.{$requestId}.{$organizationId}.{$webhookUrl}.{$payloadBody}";

        $cert     = openssl_x509_read($certificate);
        $pubKey   = openssl_pkey_get_public($cert);
        $verified = openssl_verify($signatureData, base64_decode($signature), $pubKey, OPENSSL_ALGO_SHA256);

        if ($verified === 1) {
            return true;
        }

        if ($verified === 0) {
            $this->_logger->info("The certificate was not verified.");
            return false;
        }

        $this->_logger->info("Error validating certificate " . openssl_error_string());
        return false;
    }

    protected function returnToSalsify($status)
    {
        $validStatus = [self::DISABLED, self::INVALID];
        if (!in_array($status, $validStatus, true)) {
            return $this->_resultJsonFactory->create()->setData(
                [
                    'status'  => 'error',
                    'message' => 'Unknown Error'
                ]
            );
        }

        if ($status === self::DISABLED) {
            return $this->_resultJsonFactory->create()->setData(
                [
                    'status'  => 'error',
                    'message' => 'Webhook Disabled'
                ]
            );
        }

        if ($status === self::INVALID) {
            return $this->_resultJsonFactory->create()->setData(
                [
                    'status'  => 'error',
                    'message' => 'Invalid Client ID'
                ]
            );
        }
    }
}
