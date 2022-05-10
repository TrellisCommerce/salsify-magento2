<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */

declare(strict_types=1);

namespace Trellis\Salsify\Ui\Component\Listing\Column;

use Magento\Store\Ui\Component\Listing\Column\Store\Options as StoreOptions;

class StoreViewOptions extends StoreOptions
{
    /**
     * All Store Views value
     */
    private const ALL_STORE_VIEWS = '0';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $this->currentOptions['All Store Views']['label'] = __('All Store Views');
        $this->currentOptions['All Store Views']['value'] = self::ALL_STORE_VIEWS;

        $this->generateCurrentOptions();

        $this->options = array_values($this->currentOptions);

        return $this->options;
    }
}
