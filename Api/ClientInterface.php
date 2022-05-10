<?php

namespace Trellis\Salsify\Api;

/**
 * Interface ClientInterface
 * @package Trellis\Salsify\Api
 */
interface ClientInterface
{
    /**
     * @param null $store
     * @return mixed
     */
    public function updateCachedResponse($store = null);

    /**
     * @param null $store
     * @return mixed
     */
    public function getProductFeed($store = null);

    /**
     * @param null $store
     * @return mixed
     */
    public function getAttributeFeed($store = null);

    /**
     * @param null $store
     * @return mixed
     */
    public function getReadinessReportFeed($store = null);

    /**
     * @param null $store
     * @return mixed
     */
    public function getDigitalAssetsFeed($store = null);

    /**
     * @param null $salsifyPropertyId
     * @param null $store
     * @return mixed
     */
    public function getProperty($salsifyPropertyId, $store = null);

    /**
     * @param null $page
     * @param null $perPage
     * @param null $store
     * @return mixed
     */
    public function getProperties($page, $perPage, $store = null);

    /**
     * @param null $salsifyPropertyIds
     * @param null $store
     * @return mixed
     */
    public function getPropertiesByIds($salsifyPropertyIds = [], $store = null);

    /**
     * @param null $salsifyProductId
     * @param null $store
     * @return mixed
     */
    public function getProduct($salsifyProductId, $store = null);

    /**
     * @param null $targetSchemaFilePath
     * @param null $store
     * @return mixed
     */
    public function uploadTargetSchema($targetSchemaFilePath, $store = null);
}
