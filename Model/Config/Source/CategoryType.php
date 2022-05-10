<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */

namespace Trellis\Salsify\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Trellis\Salsify\Api\CategoryInterface;

class CategoryType implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            /* For now we are not going to support pick lists
            [
                'label' => 'Pick lists',
                'value' => CategoryInterface::PICK_LISTS
            ],
            */
            [
                'label' => 'Simple strings',
                'value' => CategoryInterface::SIMPLE_STRING
            ]
        ];
    }
}
