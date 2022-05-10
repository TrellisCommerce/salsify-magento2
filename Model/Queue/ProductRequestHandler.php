<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */
namespace Trellis\Salsify\Model\Queue;

class ProductRequestHandler
{
    /**
     * @param string $simpleDataItem
     * @return string
     */
    public function process($simpleDataItem)
    {
        return $simpleDataItem . ' processed by Product handler';
    }
}