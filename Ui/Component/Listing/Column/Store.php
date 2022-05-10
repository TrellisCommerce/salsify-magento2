<?php
/**
 * @author Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */

declare(strict_types=1);

namespace Trellis\Salsify\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Magento\Store\Model\System\Store as SystemStore;
use Magento\Ui\Component\Listing\Columns\Column;

class Store extends Column
{
    /**
     * Escaper
     *
     * @var Escaper
     */
    protected $escaper;

    /**
     * System store
     *
     * @var SystemStore
     */
    protected $systemStore;

    /**
     * Store manager
     *
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * @var string
     */
    protected $storeKey;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param SystemStore $systemStore
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     * @param string $storeKey
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        SystemStore $systemStore,
        Escaper $escaper,
        array $components = [],
        array $data = [],
        $storeKey = 'store_view_id'
    ) {
        $this->systemStore = $systemStore;
        $this->escaper = $escaper;
        $this->storeKey = $storeKey;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item[$this->getData('name')] = $this->prepareItem($item);
            }
        }

        return $dataSource;
    }

    /**
     * Get data
     *
     * @param array $item
     * @return string
     */
    protected function prepareItem(array $item)
    {
        $origStores = null;
        $content = '';
        $origStores = explode(",", $item[$this->storeKey]);

        if (in_array(0, $origStores)) {
            return __('All Store Views');
        }

        $data = $this->systemStore->getStoresStructure(false, $origStores);

        foreach ($data as $website) {
            $content .= $website['label'] . "<br/>";
            foreach ($website['children'] as $group) {
                $content .= str_repeat('&nbsp;', 3) . $this->escaper->escapeHtml($group['label']) . "<br/>";
                foreach ($group['children'] as $store) {
                    $content .= str_repeat('&nbsp;', 6) . $this->escaper->escapeHtml($store['label']) . "<br/>";
                }
            }
        }

        return $content;
    }
}
