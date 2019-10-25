<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/quote-graphql
 * @link    https://github.com/scandipwa/quote-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\QuoteGraphQl\Model\Resolver;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Catalog\Model\ProductFactory;

use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;
use ScandiPWA\CatalogGraphQl\Helper\Attributes;
use ScandiPWA\CatalogGraphQl\Helper\Images;
use ScandiPWA\CatalogGraphQl\Helper\Stocks;

class GetCartForCustomer extends CartResolver
{
    /**
     * @var Configurable
     */
    protected $configurable;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var Attributes
     */
    protected $attributes;

    /**
     * @var Images
     */
    protected $images;

    /**
     * @var Stocks
     */
    protected $stocks;

    /**
     * GetCartForCustomer constructor.
     * @param ParamOverriderCustomerId $overriderCustomerId
     * @param CartManagementInterface $quoteManagement
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param Configurable $configurable
     * @param ProductFactory $productFactory
     * @param Attributes $attributes
     * @param Images $images
     * @param Stocks $stocks
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GuestCartRepositoryInterface $guestCartRepository,
        Configurable $configurable,
        ProductFactory $productFactory,
        Attributes $attributes,
        Images $images,
        Stocks $stocks
    ) {
        parent::__construct(
            $guestCartRepository,
            $overriderCustomerId,
            $quoteManagement
        );

        $this->attributes = $attributes;
        $this->images = $images;
        $this->stocks = $stocks;
        $this->configurable = $configurable;
        $this->productFactory = $productFactory;
    }

    protected function getProductData($model)
    {
        $productData = ['model' => $model];
        $id = $model->getId();

        if (isset($this->images[$id])) {
            foreach ($this->images[$id] as $imageType => $imageData) {
                $productData[$imageType] = $imageData;
            }
        }

        if (isset($this->attributes[$id])) {
            $productData['attributes'] = $this->attributes[$id];
        }

        if (isset($this->stocks[$id])) {
            foreach ($this->stocks[$id] as $stockType => $stockData) {
                $productData[$stockType] = $stockData;
            }
        }

        return $productData + $model->getData();
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return Value|CartInterface|mixed
     * @throws NotFoundException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $cart = $this->getCart($args);
        $items = $cart->getItems();
        $itemsData = [];

        // Prepare product data in advance
        $products = array_map(function ($item) {
            return $item->getProduct();
        }, $items);

        $adjustedInfo = $info->fieldNodes[0];
        $this->attributes = $this->attributes->getProductAttributes($products, $adjustedInfo);
        $this->images = $this->images->getProductImages($products, $adjustedInfo);
        $this->stocks = $this->stocks->getProductStocks($products, $adjustedInfo);

        foreach ($items as $item) {
            $product = $item->getProduct();
            $parentIds = $this->configurable->getParentIdsByChild($product->getId());

            if (count($parentIds)) {
                $parentProduct = $this->productFactory->create()->load(reset($parentIds));
                $itemsData[] = ['product' => $this->getProductData($parentProduct)] + $item->getData();
            } else {
                $itemsData[] = ['product' => $this->getProductData($product)] + $item->getData();
            }
        }

        $address = $cart->isVirtual() ? $cart->getBillingAddress() : $cart->getShippingAddress();
        $tax_amount = $address->getTaxAmount();
        $discount_amount = $address->getDiscountAmount();

        return array_merge(
            $cart->getData(),
            [
                'items' => $itemsData,
                'tax_amount' => $tax_amount,
                'discount_amount' => $discount_amount,
                /**
                 * In interface it is PHPDocumented that it returns bool,
                 * while in implementation it returns int.
                 */
                'is_virtual' => (bool)$cart->getIsVirtual()
            ]
        );
    }
}
