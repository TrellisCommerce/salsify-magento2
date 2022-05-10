<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */

declare(strict_types=1);

namespace Trellis\Salsify\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SimpleDataObjectConverter;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Trellis\Salsify\Api\Data\PayloadInterface;
use Trellis\Salsify\Api\Data\PayloadSearchResultsInterface;
use Trellis\Salsify\Api\Data\PayloadSearchResultsInterfaceFactory;
use Trellis\Salsify\Api\PayloadRepositoryInterface;
use Trellis\Salsify\Model\PayloadFactory;
use Trellis\Salsify\Model\ResourceModel\Payload as PayloadResource;
use Trellis\Salsify\Model\ResourceModel\Payload\Collection;
use Trellis\Salsify\Model\ResourceModel\Payload\CollectionFactory;

class PayloadRepository implements PayloadRepositoryInterface
{
    /**
     * @var PayloadFactory
     */
    private $payloadFactory;
    /**
     * @var PayloadResource
     */
    private $payloadResource;
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;
    /**
     * @var PayloadSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;
    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    public function __construct(
        PayloadFactory $payloadFactory,
        PayloadResource $payloadResource,
        CollectionFactory $collectionFactory,
        PayloadSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->payloadFactory = $payloadFactory;
        $this->payloadResource = $payloadResource;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * Retrieve Payload.
     *
     * @param $entityId
     * @return Payload
     * @throws NoSuchEntityException
     */
    public function get($entityId): Payload
    {
        $payload = $this->payloadFactory->create();
        $this->payloadResource->load($payload, $entityId);
        if (!$payload->getId()) {
            throw NoSuchEntityException::singleField(
                SimpleDataObjectConverter::snakeCaseToCamelCase(PayloadInterface::ENTITY_ID),
                $entityId
            );
        }

        return $payload;
    }

    /**
     * @param Payload $object
     * @return Payload
     * @throws CouldNotSaveException
     */
    public function save(Payload $object): Payload
    {
        try {
            $this->payloadResource->save($object);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save payload: %1', $exception->getMessage()),
                $exception
            );
        }

        return $object;
    }

    /**
     * @param Payload $object
     * @throws CouldNotDeleteException
     */
    public function delete(Payload $object): void
    {
        try {
            $this->payloadResource->delete($object);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete history item: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    /**
     * @param int $entityId
     *
     * @throws CouldNotDeleteException
     */
    public function deleteById($entityId): void
    {
        try {
            $this->payloadResource->delete($this->get($entityId));
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the history item: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return PayloadSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var PayloadSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResults->setTotalCount($collection->getSize());
        $searchResults->setItems($collection->getItems());

        return $searchResults;
    }
}
