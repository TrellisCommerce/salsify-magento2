<?php

declare(strict_types=1);

namespace Trellis\Salsify\Api\Data;

interface PayloadSearchResultsInterface
{
    /**
     * Get items list.
     *
     * @return PayloadInterface[]
     */
    public function getItems();

    /**
     * Set items list.
     *
     * @param PayloadInterface[] $items
     *
     * @return PayloadInterface
     */
    public function setItems(array $items);
}
