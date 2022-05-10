<?php
/**
 * @author    Trellis Team
 * @copyright Copyright © Trellis (https://www.trellis.co)
 */

namespace Trellis\Salsify\Model\Sync;

use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory as ProductLinkFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\Product\Initialization\Helper\ProductLinks as ProductLinksHelper;
use Magento\Catalog\Model\Product\LinkTypeProvider;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Trellis\Salsify\Logger\Logger;

class ProductLinks
{

    /** @var Logger $logger */
    private $logger;
    /**
     * @var ProductLinksHelper
     */
    private $productLinks;
    /**
     * @var LinkTypeProvider
     */
    private $linkTypeProvider;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var ProductLinkFactory
     */
    private $productLinkFactory;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * ProductLinks constructor.
     *
     * @param ProductLinksHelper         $productLinks
     * @param LinkTypeProvider           $linkTypeProvider
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder      $searchCriteriaBuilder
     * @param ProductLinkFactory         $productLinkFactory
     * @param Logger                     $logger
     */
    public function __construct(
        ProductLinksHelper $productLinks,
        LinkTypeProvider $linkTypeProvider,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductLinkFactory $productLinkFactory,
        Logger $logger
    ) {
        $this->productLinks = $productLinks;
        $this->linkTypeProvider = $linkTypeProvider;
        $this->productRepository = $productRepository;
        $this->productLinkFactory = $productLinkFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Setting product links
     *
     * @param ProductModel $product
     * @param              $skuList
     * @param              $linkTypeParam
     *
     * @return ProductModel
     */
    public function setProductLinks(ProductModel $product, $skuList, $linkTypeParam)
    {

        $product->setProductLinks([]);
        $links = $this->getLinksList($skuList, $linkTypeParam);
        $product = $this->productLinks->initializeLinks($product, $links);
        $productLinks = $product->getProductLinks();
        $linkTypes = [];

        /** @var \Magento\Catalog\Api\Data\ProductLinkTypeInterface $linkTypeObject */
        foreach ($this->linkTypeProvider->getItems() as $linkTypeObject) {
            $linkTypes[$linkTypeObject->getName()] = $product->getData($linkTypeObject->getName() . '_readonly');
        }

        // skip linkTypes that were already processed on initializeLinks plugins
        foreach ($productLinks as $productLink) {
            unset($linkTypes[$productLink->getLinkType()]);
        }

        foreach ($linkTypes as $linkType => $readonly) {
            if (isset($links[$linkType]) && !$readonly) {
                foreach ((array) $links[$linkType] as $linkData) {
                    if (empty($linkData['id'])) {
                        continue;
                    }

                    try {
                        $linkProduct = $this->productRepository->get($linkData['id']);
                    } catch (NoSuchEntityException $e) {
                        $this->logger->addError($e->getMessage());
                        $linkProduct = null;
                    }
                    if ($linkProduct) {
                        $link = $this->productLinkFactory->create();
                        $link->setSku($product->getSku())
                            ->setLinkedProductSku($linkProduct->getSku())
                            ->setLinkType($linkType)
                            ->setPosition(isset($linkData['position']) ? (int) $linkData['position'] : 0);
                        $productLinks[] = $link;
                    }

                }
            }
        }

        try {
            $product->setProductLinks($productLinks);
            $this->productRepository->save($product);
        } catch (\Exception $exception) {
            $this->logger->addError($exception->getMessage());
        }

        return $product;
    }

    /**
     * @param $skuList
     * @param $type
     *
     * @return array
     */
    private function getLinksList($skuList, $type)
    {
        /** @var SearchCriteriaInterface $searchCriteria */
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('sku', $skuList, 'in')
            ->create();

        $values = [];
        $list = $this->productRepository->getList($searchCriteria);
        if ($list->getTotalCount() > 0) {
            $values = array_map(function ($row) {
                /** @var \Magento\Catalog\Api\Data\ProductInterface $row */
                return [
                    'id'         => $row->getId(),
                    'name'       => $row->getName(),
                    'sku'        => $row->getSku(),
                    'price'      => $row->getPrice(),
                    'type_id'    => $row->getTypeId(),
                    'position'   => 0,
                    'initialize' => 'true',
                    'record_id'  => $row->getId()
                ];
            }, $list->getItems());
        }

        return [$type => array_values($values)];
    }
}
