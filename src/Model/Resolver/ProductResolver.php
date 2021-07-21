<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/quote-graphql
 * @link https://github.com/scandipwa/quote-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\App\Emulation;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Catalog\Helper\Image as HelperImage;

/**
 * Retrieves the Product list in orders
 */
class ProductResolver implements ResolverInterface
{
    use ResolveInfoFieldsTrait;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var Product
     */
    protected $productDataProvider;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var DataPostProcessor
     */
    protected $postProcessor;

    /**
     * @var AttributeFactory
     */
    protected AttributeFactory $attributeFactory;

    /**
     * @var Emulation
     */
    protected $emulation;

    /**
     * @var HelperImage
     */
    protected $helperImage;

    /**
     * ProductResolver constructor.
     * @param ProductRepository $productRepository
     * @param Product $productDataProvider
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataPostProcessor $postProcessor
     * @param AttributeFactory $attributeFactory
     * @param Emulation $emulation
     * @param HelperImage $helperImage
     */
    public function __construct(
        ProductRepository $productRepository,
        Product $productDataProvider,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DataPostProcessor $postProcessor,
        AttributeFactory $attributeFactory,
        Emulation $emulation,
        HelperImage $helperImage
    ) {
        $this->productRepository = $productRepository;
        $this->productDataProvider = $productDataProvider;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->postProcessor = $postProcessor;
        $this->attributeFactory = $attributeFactory;
        $this->emulation = $emulation;
        $this->helperImage = $helperImage;
    }

    /**
     * Get All Product Items of Order.
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['products'])) {
            return [];
        }

        $productIds = array_map(function ($item) {
            return $item['product_id'];
        }, $value['products']);

        $attributeCodes = $this->getFieldsFromProductInfo($info, 'order_products');

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->create();

        $products = $this->productDataProvider
            ->getList(
                $searchCriteria,
                $attributeCodes,
                false,
                true,
                null,
                false,
                true
            )
            ->getItems();

        $productsData = $this->postProcessor->process(
            $products,
            'order_products',
            $info
        );

        $data = [];

        foreach ($value['products'] as $key => $item) {
            $productId = $item->getProductId();

            if (isset($productsData[$productId])) {
                $productItem = $productsData[$productId];
            } else {
                // product was deleted, return the empty one
                $productItem = $this->getEmptyProductItem($item);
            }

            /** @var $item Item */
            $data[$key] = $productItem;
            $data[$key]['qty'] = $item->getQtyOrdered();
            $data[$key]['row_total'] = $item->getRowTotalInclTax();
            $data[$key]['original_price'] = $item->getOriginalPrice();
            $data[$key]['license_key'] = $item['license_key'];
        }

        return $data;
    }

    /**
     * Get empty product item
     * @param Item $item
     * @return array
     */
    protected function getEmptyProductItem(Item $item): array
    {
        $this->emulation->startEnvironmentEmulation(
            (int)$item->getStoreId(),
            Area::AREA_FRONTEND,
            true);

        $result = [
            'name' => $item->getName(),
            'entity_id' => $item->getProductId(),
            'type_id' => 'simple',
            'model' => $this->attributeFactory->create(),
            'image' => [
                'path' => '',
                'label' => '',
                'url' => $this->helperImage->getDefaultPlaceholderUrl('image')
            ],
            'small_image' => [
                'path' => '',
                'label' => '',
                'url' => $this->helperImage->getDefaultPlaceholderUrl('small_image')
            ],
            'thumbnail' => [
                'path' => '',
                'label' => '',
                'url' => $this->helperImage->getDefaultPlaceholderUrl('thumbnail')
            ]
        ];

        $this->emulation->stopEnvironmentEmulation();

        return $result;
    }
}
