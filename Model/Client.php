<?php

namespace Trellis\Salsify\Model;

use Magento\Framework\Exception\IntegrationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Trellis\Salsify\Api\ClientInterface;
use Trellis\Salsify\Helper\Data;
use Trellis\Salsify\Helper\ProductFeed;
use Trellis\Salsify\Helper\ReadinessReports;
use Trellis\Salsify\Logger\Logger;
use Trellis\Salsify\Model\PayloadFactory;
use Trellis\Salsify\Model\SalsifyRecordFactory;
use Trellis\Salsify\Api\Data\SalsifyRecordInterface;
use Trellis\Salsify\Api\Data\SalsifyProductInterface;
use Trellis\Salsify\Model\Record\SalsifyProductFactory;
use Trellis\Salsify\Model\SalsifyRecordCollectionFactory as CollectionFactory;
use Trellis\Salsify\Model\SalsifyRecordCollection as RecordsCollection;
use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Api\SimpleDataObjectConverter;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Client
 *
 * @package Trellis\Salsify\Model
 */
class Client extends Curl implements ClientInterface
{

    private $stringUtils;

    private $serializer;
    private $arrayManager;
    private $salsifyProductFactory;

    /** @var SalsifyRecordFactory */
    private $salsifyRecordFactory;

    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var RecordsCollection */
    private $recordsCollection;

    /**
     * @var Data
     */
    protected $_dataHelper;
    protected $_readinessReportsHelper;
    protected $_productFeedHelper;

    /**
     * @var UrlInterface
     */
    protected $_url;
    /**
     *
     */
    const PRODUCT_EXPORT_URL_KEY = 'product_export_url';
    /**
     *
     */
    const WEBHOOK_PRODUCT_EXPORT_URL_KEY = 'product_feed_export_url';
    /**
     *
     */
    const WEBHOOK_STATUS = 'publication_status';
    /**
     *
     */
    const PRODUCTS_KEY = 'products';
    /**
     *
     */
    const ATTRIBUTES_KEY = 'attributes';
    /**
     *
     */
    const DIGITAL_ASSETS_KEY = 'digital_assets';

    const CHANNEL_RUNS_PATH = 'channels/:channel_id/runs/latest';

    const BASE_URL = 'https://app.salsify.com/api/orgs/';
    const BASE_URL_V1 = 'https://app.salsify.com/api/v1/orgs/';

    protected $_feedUrl;

    /**
     * @var bool
     */
    protected $_requested = false;
    /**
     * @var null
     */
    protected $_salsifyResponse = null;
    /**
     * @var null
     */
    protected $_productFeedResponse = null;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var PayloadFactory
     */
    protected $_payloadFactory;
    /**
     * @var DateTime
     */
    protected $_datetime;

    /**
     * Client constructor.
     *
     * @param Data                                        $dataHelper
     * @param ReadinessReports                            $readinessReportsHelper
     * @param ProductFeed                                 $productFeedHelper
     * @param UrlInterface                                $url
     * @param Logger                                      $logger
     * @param \Trellis\Salsify\Model\PayloadFactory       $payloadFactory
     * @param DateTime                                    $datetime
     * @param \Trellis\Salsify\Model\SalsifyRecordFactory $salsifyRecordFactory
     * @param SalsifyRecordCollectionFactory              $collectionFactory
     * @param SalsifyRecordCollection                     $recordsCollection
     * @param StringUtils                                 $stringUtils
     * @param SalsifyProductFactory                       $salsifyProductFactory
     * @param Json                                        $serializer
     * @param ArrayManager                                $arrayManager
     */

    public function __construct(
        Data $dataHelper,
        ReadinessReports $readinessReportsHelper,
        ProductFeed $productFeedHelper,
        UrlInterface $url,
        Logger $logger,
        PayloadFactory $payloadFactory,
        DateTime $datetime,
        SalsifyRecordFactory $salsifyRecordFactory,
        CollectionFactory $collectionFactory,
        RecordsCollection $recordsCollection,
        StringUtils $stringUtils,
        SalsifyProductFactory $salsifyProductFactory,
        Json $serializer,
        ArrayManager $arrayManager
    ) {
        $this->_dataHelper = $dataHelper;
        $this->_readinessReportsHelper = $readinessReportsHelper;
        $this->_productFeedHelper = $productFeedHelper;
        $this->_url = $url;
        $this->logger = $logger;
        $this->_payloadFactory = $payloadFactory;
        $this->_datetime = $datetime;
        $this->salsifyRecordFactory = $salsifyRecordFactory;
        $this->collectionFactory = $collectionFactory;
        $this->recordsCollection = $recordsCollection;
        $this->stringUtils = $stringUtils;
        $this->serializer = $serializer;
        $this->salsifyProductFactory = $salsifyProductFactory;
        $this->arrayManager = $arrayManager;

        $headers = [
            'Origin'       => $this->_url->getBaseUrl(),
            'Content-Type' => 'application/json',
            'Accepts'      => 'application/json',
        ];
        $this->setHeaders($headers);

        $this->_addAuthorizationHeader();

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) ($this->_dataHelper->getTimeout()),
            CURLOPT_SSL_VERIFYPEER => false,
        ];
        $this->setOptions($options);
    }

    /**
     * @param null $store
     *
     * @throws IntegrationException
     */
    public function updateCachedResponse($store = null)
    {
        $url = $this->_getBaseUrl($store) . str_replace(':channel_id', $this->_productFeedHelper->getChannelId($store), self::CHANNEL_RUNS_PATH) . "?format=json";
        $this->makeRequest('GET', $url);
        if ($this->getStatus() != 200) {
            throw new IntegrationException(__('Could not retrieve product feed.'));
        }
        $this->_salsifyResponse = json_decode($this->getBody(), true);

        // We have to remove the authorization header when requesting the product feed because only one auth method is allowed.
        // The self::PRODUCT_EXPORT_URL_KEY already contains an auth key in the query string param.
        $this->removeHeader("Authorization");

        $this->makeRequest('GET', $this->_salsifyResponse[self::PRODUCT_EXPORT_URL_KEY]);
        if ($this->getStatus() != 200) {
            throw new IntegrationException(__('Could not retrieve product export list.'));
        }

        // Add the header back for subsequent requests
        $this->_addAuthorizationHeader();

        $this->setProductFeedResponse(json_decode($this->getBody(), true));
        $this->logger->info("--- SALSIFY FEED PUBLISHED BY: " . $this->_salsifyResponse['creator']['email']);
        $this->logger->info("--- SALSIFY FEED PUBLISHED AT: " . $this->_salsifyResponse['ended_at']);
        $this->_requested = true;
    }

    /**
     * @param null $data
     * @return $this
     */
    private function setProductFeedResponse($data = null): self {
        if(!is_array($data)){
            $this->_productFeedResponse = [];
            return $this;
        }
        $this->_productFeedResponse = $data;
        return $this;
    }

    /**
     * @param null $store
     *
     * @return null
     * @throws IntegrationException
     */
    public function getProductFeed($store = null)
    {
        return $this->_iterateProductFeedResponseForKey(self::PRODUCTS_KEY, $store);
    }

    /**
     * @param null $store
     *
     * @return null
     * @throws IntegrationException
     */
    public function getAttributeFeed($store = null)
    {
        return $this->_iterateProductFeedResponseForKey(self::ATTRIBUTES_KEY, $store);
    }

    /**
     * Returns the first record that has not been processed in the webhook payload
     *
     * @throws IntegrationException
     */
    private function getWebHookSavedPayloads()
    {

        $product_feed = '';
        $payload_id = '';
        $throw_err = true;
        $payload = $this->_payloadFactory->create();
        $collection = $payload->getCollection()
            ->addFieldToSelect('*')
            ->addFieldToFilter('processed', ['eq' => '0'])
            ->setPageSize(1)
            ->setCurPage(1)
            ->load();

        if ($collection->getSize()) {
            $this->logger->info('salsify: payload collection found');
            $payload_data = $collection->getData()[0];
            $payload_jsondata = json_decode($payload_data['payload'], true);

            if (array_key_exists(self::WEBHOOK_STATUS, $payload_jsondata) && array_key_exists(self::WEBHOOK_PRODUCT_EXPORT_URL_KEY,
                    $payload_jsondata)) {
                if ($payload_jsondata[self::WEBHOOK_STATUS] == 'completed') {

                    $this->logger->info('salsify: grabbing url');

                    // Grabbing the product feed and marking the record as processed.
                    $product_feed = $payload_jsondata[self::WEBHOOK_PRODUCT_EXPORT_URL_KEY];
                    $this->logger->info('salsify: ' . $product_feed);

                    $this->removeHeader("Authorization");
                    $this->makeRequest('GET', $product_feed);

                    $this->_feedUrl = $product_feed;

                    $response = json_decode($this->getBody(), true);
                    $this->logger->info('salsify: ' . $this->getBody());
                    $this->logger->info('salsify: ' . $this->getStatus());

                    if ($this->getStatus() == 200) {
                        // Setting the payload as processed.
                        $payload->load($payload_data['entity_id']);
                        $payload_data['processed_at'] = $this->_datetime->gmtDate();
                        $payload_data['processed'] = 1;
                        $payload->setData($payload_data);
                        $payload->save();

                        $throw_err = false;

                    }

                }
            }

        }

        // Errors out if none of the product exports result in a response code of 200.
        if ($throw_err == true) {
            throw new IntegrationException(__('Could not retrieve product export list from webhook payload.'));
        }

    }

    /**
     * Sets readiness reports / product export data from salsify.
     *
     * @param null $store
     *
     * @throws IntegrationException
     */
    private function getReadinessReportRunsFeed($store = null)
    {

        $run_ids = [];
        $throw_err = true;

        // Added code to remove the latest flag from readiness report.
        $url = $this->_getBaseUrl()
            . str_replace(
                '/latest',
                '',
                str_replace(
                    ':channel_id',
                    $this->_readinessReportsHelper->getChannelId($store),
                    self::CHANNEL_RUNS_PATH)
            )
            . "?format=json";

        $this->makeRequest('GET', $url);
        $response = json_decode($this->getBody(), true);

        // Errors out if response is not a response code of 200 from the runs url
        if ($this->getStatus() != 200) {
            throw new IntegrationException(__('Could not retrieve readiness report runs feed.'));
        }

        // Needed grab the ids and product export url for completed runs.
        foreach ($response['runs'] as $run_i => $run_item) {
            if ($run_item['status'] == 'completed') {
                $run_ids[$run_item['id']] = $run_item[self::PRODUCT_EXPORT_URL_KEY];
            } else {
                continue;
            }
        }

        // We have to remove the authorization header when requesting the product feed because only one auth method is allowed.
        // The self::PRODUCT_EXPORT_URL_KEY already contains an auth key in the query string param.
        $this->removeHeader("Authorization");

        // This is needed to loop through the payload to see what is the latest. If an error happens this will revert to the last good product export.
        krsort($run_ids, SORT_NUMERIC);
        foreach ($run_ids as $prr_id => $prr_url) {
            $this->logger->info('salsify run id: ' . $prr_id);
            $this->logger->info('salsify product export url: ' . $prr_url);
            $this->makeRequest('GET', $prr_url);
            if ($this->getStatus() == 200) {
                $throw_err = false;

                $this->_feedUrl = $prr_url;
                break;
            } else {
                // Next Product Export
                $this->logger->info('salsify: Skipping product export url because of error. Trying next completed product export...');
                continue;
            }
        }

        // Errors out if none of the product exports result in a response code of 200.
        if ($throw_err == true) {
            throw new IntegrationException(__('Could not retrieve product export list.'));
        }

    }

    /**
     * Main function for readiness reports sync.
     *
     * @param null $store
     *
     * @return mixed
     * @throws IntegrationException
     */
    public function getReadinessReportFeed($store = null, $sync_type = 'admin')
    {

        if ($sync_type == 'admin') {
            // Grabs the product export from the runs url
            $this->logger->info('salsify: grabbing manual sync');
            $this->getReadinessReportRunsFeed($store);
        } else {
            $this->logger->info('salsify: grabbing stored webhook payload');
            // Grabs the product export from webhook payloads store in the database
            $this->getWebHookSavedPayloads();
        }

        // Add the header back for subsequent requests
        $this->_addAuthorizationHeader();

        if (($this->getBody()) && $response = $this->getSerializer()->unserialize($this->getBody())) {
            $this->logger->info('salsify:' . $this->getBody());
            try {
                return $this->getResult($response);
            } catch (\Exception $e) {
                return $this->getLegacyResult($response);
            }
        }

        return $this->getResult();
    }

    /**
     * @return Json
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * @param null $response
     *
     * @return SalsifyRecordCollection
     * @throws LocalizedException
     */
    public function getProcessedBody($response = null)
    {
        if ($response === null && $this->recordsCollection) {
            return $this->recordsCollection;
        }
        $collection = $this->collectionFactory->create();

        if (!$response) {
            throw new LocalizedException(new Phrase('Empty response from client'));
        }
        foreach ($response as $i => $data) {
            $do = $this->salsifyRecordFactory->create();
            foreach ($data as $k => $v) {
                if (is_array($v)) {
                    $arr = [];
                    foreach ($v as $dex => $value) {
                        $arr[trim($dex)] = array_key_exists('value',current($value)) ? trim(current($value)['value']): current($value);
                    }
                    $do->setData(trim($k), $this->salsifyProductFactory->create(['data' => $arr]));
                } else {
                    $do->setData(trim($k), trim($v));

                }
            }
            $do->setId($i);
            try {
                $collection->addItem($do);
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage());
            }

        }
        $this->recordsCollection = $collection;

        return $this->recordsCollection;

    }

    public function getResult($response = null, bool $asCollection = false)
    {

        $result = [];
        $body = null;
        try {
            $body = $this->getProcessedBody($response);
            if ($asCollection === true) {
                return $body;
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
        if ($body && $body->getSize()) {
            $iterator = $body->getIterator();
            foreach ($iterator as $i) {
                $result[$i->getId()] = $i->getProduct()->toArray();
            }
        }

        return $result;
    }

    /**
     * @param null $response
     *
     * @return array
     */
    public function getLegacyResult($response = null)
    {
        $result = [];
        if ($response) {
            foreach ($response as $i => $item) {
                $result[$i] = [];
                foreach ($item['product'] as $attribute => $values) {
                    if (is_array($values)) {
                        if (count($values) > 1) {
                            foreach ($values as $value) {
                                $result[$i][$attribute][] = array_key_exists('value',$value)? trim($value['value']) : $value;
                            }
                        } else {
                            $result[$i][$attribute] = array_key_exists('value',$values[0]) ? trim($values[0]['value']): $values[0];
                        }
                    } else {
                        $result[$i][$attribute] = trim($values);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param null $store
     *
     * @return null
     * @throws IntegrationException
     */
    public function getDigitalAssetsFeed($store = null)
    {
        return $this->_iterateProductFeedResponseForKey(self::DIGITAL_ASSETS_KEY, $store);
    }

    /**
     * Get a single property from Salsify
     *
     * @param string $salsifyPropertyId
     * @param null   $store
     *
     * @return mixed|null
     */
    public function getProperty($salsifyPropertyId, $store = null)
    {
        return $this->getPropertiesByIds([$salsifyPropertyId], $store);
    }

    /**
     * Get all properties from the Salsify API
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getProperties($page, $perPage, $store = null)
    {
        $params = [
            'use_new_serialiazation_format' => 'true',
            'serialize_system_ids'          => 'true',
            'query'                         => '',
            'page'                          => $page,
            'per_page'                      => $perPage,
            'include_relation_type'         => 'true',
        ];

        $queryParams = http_build_query($params);

        $url = "{$this->_getBaseUrl($store)}properties?{$queryParams}";
        $this->makeRequest('GET', $url);

        return $this->getSerializer()->unserialize($this->getBody());
    }

    /**
     * Get multiple properties from Salsify
     *
     * @param array $salsifyPropertyIds
     * @param null  $store
     *
     * @return mixed|null
     */
    public function getPropertiesByIds($salsifyPropertyIds = [], $store = null)
    {
        $params = [
            'ids' => $salsifyPropertyIds
        ];

        // Must set CURLOPT_POSTFIELDS ourselves because the makeRequest() method only sets CURLOPT_POSTFIELDS if a POST request is made.
        $this->setOption(CURLOPT_POSTFIELDS, json_encode($params));

        $url = "{$this->_getBaseUrlV1($store)}properties";
        $this->makeRequest('REPORT', $url);

        $this->_salsifyResponse = json_decode($this->getBody(), true);

        return $this->_salsifyResponse;
    }

    /**
     * Get a single product from the Salsify API.
     *
     * @param string $salsifyProductId
     * @param null   $store
     *
     * @return mixed
     */
    public function getProduct($salsifyProductId, $store = null)
    {
        $this->setOptions([]);

        $url = "{$this->_getBaseUrlV1($store)}products/{$salsifyProductId}";
        $this->makeRequest('GET', $url);

        return json_decode($this->getBody(), true);
    }

    public function uploadTargetSchema($targetSchemaFilePath, $store = null)
    {
        $this->setOptions([]);
        $this->addHeader('Authorization', "Bearer {$this->_readinessReportsHelper->getToken($store)}");
        $this->addHeader('Cache-Control', "no-cache");
        $this->addHeader('Content-Type', "application/json");

        $params = [
            'target_schema_external_id' => $this->_readinessReportsHelper->getTargetSchemaExternalId($store),
            'target_schema_url'         => $this->_url->getBaseUrl() . $targetSchemaFilePath,
            'organization_id'           => $this->_readinessReportsHelper->getOrganizationId($store),
            'target_schema_name'        => $this->_readinessReportsHelper->getTargetSchemaName($store),
            'destination_domain'        => $this->_url->getBaseUrl(),
            'logo_url'                  => '',
            'callback'                  => $this->_readinessReportsHelper->getTargetSchemaCallbackUrl($store),
            'service_name'              => $this->_readinessReportsHelper->getTargetSchemaServiceName($store),
        ];

        $this->makeRequest('POST', 'https://ts-validation-proxy.internal.salsify.com/api/target_schema_imports', json_encode($params));

        return json_decode($this->getBody(), true);
    }

    /**
     * Get the feed url that was used to grab the product data from Salsify.
     *
     * @return mixed
     */
    public function getFeedUrl()
    {
        return $this->_feedUrl;
    }

    protected function _getBaseUrl($store = null)
    {
        return self::BASE_URL . $this->_dataHelper->getOrganizationId($store) . '/';
    }

    protected function _getBaseUrlV1($store = null)
    {
        return self::BASE_URL_V1 . $this->_dataHelper->getOrganizationId($store) . '/';
    }

    protected function _addAuthorizationHeader()
    {
        $this->addHeader('Authorization', "Bearer {$this->_dataHelper->getApiKey()}");
    }

    /**
     * @param      $key
     * @param null $store
     *
     * @return null
     * @throws IntegrationException
     */
    protected function _iterateProductFeedResponseForKey($key, $store = null)
    {
        if (!$this->_requested) {
            $this->updateCachedResponse($store);
        }
        $result = null;
        $len = count($this->_productFeedResponse);
        for ($i = 0; $i < $len; $i++) {
            if (array_key_exists($key, $this->_productFeedResponse[$i])) {
                $result = $this->_productFeedResponse[$i][$key];
                break;
            }
        }
        if ($result === null) {
            throw new IntegrationException(__("Could not find {$key} within response."));
        }

        return $result;
    }
}
