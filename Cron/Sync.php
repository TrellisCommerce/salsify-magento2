<?php

namespace Trellis\Salsify\Cron;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CronException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\FlagManager;
use Trellis\Salsify\Api\PayloadRepositoryInterface;
use Trellis\Salsify\Helper\Data;
use Trellis\Salsify\Logger\Logger;
use Trellis\Salsify\Model\Sync\ClearSalsifyId;
use Trellis\Salsify\Model\Sync\ProductFeed;
use Trellis\Salsify\Model\Sync\ReadinessReport;

class Sync
{
    private const CONTEXT_BUTTON = 'manual:button';
    private const CONTEXT_CRON = 'cron:run';
    private const FLAG = 'salsify_lock';

    public const SYNC_TYPE_PRODUCT_FEED = 'product_feed';
    public const SYNC_TYPE_CLEAR_SALSIFY_ID = 'clear_salsify_id';
    public const SYNC_TYPE_READINESS_REPORT = 'readiness_report';

    /**
     * @var Data
     */
    protected $data;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var ProductFeed
     */
    protected $salsifyProductFeedSync;
    /**
     * @var ReadinessReport
     */
    protected $salsifyReadinessReportSync;
    /**
     * @var ClearSalsifyId
     */
    private $clearSalsifyId;
    /**
     * @var PayloadRepositoryInterface
     */
    private $payloadRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var FlagManager
     */
    private $flagManager;

    /**
     * Sync constructor.
     *
     * @param Data $data
     * @param Logger $logger
     * @param ProductFeed $productFeedSync
     * @param ReadinessReport $readinessReportSync
     * @param ClearSalsifyId $clearSalsifyId
     * @param PayloadRepositoryInterface $payloadRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FlagManager $flagManager
     */
    public function __construct(
        Data $data,
        Logger $logger,
        ProductFeed $productFeedSync,
        ReadinessReport $readinessReportSync,
        ClearSalsifyId $clearSalsifyId,
        PayloadRepositoryInterface $payloadRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FlagManager $flagManager
    ) {
        $this->data                       = $data;
        $this->logger                     = $logger;
        $this->salsifyProductFeedSync     = $productFeedSync;
        $this->salsifyReadinessReportSync = $readinessReportSync;
        $this->clearSalsifyId             = $clearSalsifyId;
        $this->payloadRepository = $payloadRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->flagManager = $flagManager;
    }

    /**
     * @param object|string $context
     * @param string $syncType
     *
     * @throws LocalizedException
     */
    public function execute($context, $syncType = self::SYNC_TYPE_READINESS_REPORT): void
    {
        $context = is_object($context) ? self::CONTEXT_CRON : $context;
        $pid = $this->flagManager->getFlagData(self::FLAG);
        if ($pid && posix_kill($pid, 0)) {
            $this->logger->info('Job is already running');
            throw new CronException(__('Job is already running'));
        }
        $this->flagManager->deleteFlag(self::FLAG);
        $manual = false;

        if ($context === self::CONTEXT_CRON && !$this->data->getWebhookEnabled()) {
            return;
        }

        // Create the lock flag before trying to sync so that subsequent cron jobs won't also attempt to sync
        if ($this->hasAvailablePayload()) {
            $this->logger->info('Creating lock flag...');
            $pid = getmypid();
            $this->flagManager->saveFlag(self::FLAG, $pid);
        }

        if ($context === self::CONTEXT_CRON && $pid === null) {
            $this->logger->info('No lock flag found, aborting...');
            return;
        }

        $this->logger->info('Running sync...');
        $sync = $this->getSyncInstance($syncType);

        if (($syncType === self::SYNC_TYPE_CLEAR_SALSIFY_ID || $syncType === self::SYNC_TYPE_READINESS_REPORT)
            && $context === self::CONTEXT_BUTTON) {
            $manual = true;
        }

        try {
            $sync->execute($manual);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            if($context == self::CONTEXT_BUTTON){
                $this->flagManager->deleteFlag(self::FLAG);
                throw $exception;
            }
        }
        $this->flagManager->deleteFlag(self::FLAG);
    }

    /**
     * @return bool
     */
    private function hasAvailablePayload(): bool
    {
        $criteria = $this->searchCriteriaBuilder->addFilter('processed', '0')->create();
        $criteria->setPageSize(1)->setCurrentPage(1);
        $items = $this->payloadRepository->getList($criteria)->getItems();

        return (bool) count($items);
    }

    /**
     * @param $syncType
     * @return ClearSalsifyId|ProductFeed|ReadinessReport
     * @throws \Exception
     */
    private function getSyncInstance($syncType)
    {
        switch ($syncType) {
            case self::SYNC_TYPE_PRODUCT_FEED:
                $sync = $this->salsifyProductFeedSync;
                break;
            case self::SYNC_TYPE_READINESS_REPORT:
                $sync = $this->salsifyReadinessReportSync;
                break;
            case self::SYNC_TYPE_CLEAR_SALSIFY_ID:
                $sync = $this->clearSalsifyId;
                break;
            default:
                throw new \Exception("Invalid sync type {$syncType} specified.");
        }

        return $sync;
    }
}
