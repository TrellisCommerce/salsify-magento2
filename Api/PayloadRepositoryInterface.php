<?php

namespace Trellis\Salsify\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Trellis\Salsify\Api\Data\PayloadSearchResultsInterface;
use Trellis\Salsify\Model\Payload;

interface PayloadRepositoryInterface
{
    /**
     * Retrieve Payload.
     *
     * @param $entityId
     * @return Payload
     * @throws NoSuchEntityException
     */
    public function get($entityId): Payload;

    /**
     * @param Payload $object
     * @return Payload
     * @throws CouldNotSaveException
     */
    public function save(Payload $object);

    /**
     * @param Payload $object
     * @throws CouldNotDeleteException
     */
    public function delete(Payload $object);

    /**
     * @param int $entityId
     *
     * @throws CouldNotDeleteException
     */
    public function deleteById($entityId);

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return PayloadSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
