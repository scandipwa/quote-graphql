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
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Sales\Model\Order\Item;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;

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
     * ProductResolver constructor.
     * @param ProductRepository $productRepository
     * @param Product $productDataProvider
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataPostProcessor $postProcessor
     */
    public function __construct(
        ProductRepository $productRepository,
        Product $productDataProvider,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DataPostProcessor $postProcessor
    ) {
        $this->productRepository = $productRepository;
        $this->productDataProvider = $productDataProvider;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->postProcessor = $postProcessor;
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
                false,
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
            /** @var $item Item */
            $data[$key] = $productsData[$item->getProductId()];
            $data[$key]['qty'] = $item->getQtyOrdered();
            $data[$key]['row_total'] = $item->getBaseRowTotalInclTax();
            $data[$key]['original_price'] = $item->getBaseOriginalPrice();
            $data[$key]['license_key'] = $item['license_key'];
        }

        return $data;
    }
}
