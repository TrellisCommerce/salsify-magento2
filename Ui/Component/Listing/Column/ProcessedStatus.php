<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */

declare(strict_types=1);

namespace Trellis\Salsify\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class ProcessedStatus implements OptionSourceInterface
{
    /**
     * Get Grid row status type labels array.
     *
     * @return array
     */
    public function getOptionArray()
    {
        return [
            '1' => __('Yes'),
            '0' => __('No')
        ];
    }

    /**
     * Get Grid row type array for option element.
     *
     * @return array
     */
    public function getOptions()
    {
        $res = [];
        foreach ($this->getOptionArray() as $index => $value) {
            $res[] = ['value' => $index, 'label' => $value];
        }
        return $res;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getOptions();
    }
}
