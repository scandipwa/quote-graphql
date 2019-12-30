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
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;
use ScandiPWA\Performance\Model\Resolver\ProductPostProcessor;

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
     * @var ProductPostProcessor
     */
    protected $productPostProcessor;

    /**
     * @var array
     */
    protected $productsData;

    /**
     * GetCartForCustomer constructor.
     * @param ParamOverriderCustomerId $overriderCustomerId
     * @param CartManagementInterface $quoteManagement
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param Configurable $configurable
     * @param ProductFactory $productFactory
     * @param ProductPostProcessor $productPostProcessor
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GuestCartRepositoryInterface $guestCartRepository,
        Configurable $configurable,
        ProductFactory $productFactory,
        ProductPostProcessor $productPostProcessor
    ) {
        parent::__construct(
            $guestCartRepository,
            $overriderCustomerId,
            $quoteManagement
        );

        $this->configurable = $configurable;
        $this->productFactory = $productFactory;
        $this->productPostProcessor = $productPostProcessor;
    }

    /**
     * @param QuoteItem $item
     * @param Product $product
     * @return array
     */
    protected function mergeQuoteItemData(
        QuoteItem $item,
        Product $product
    ) {
        return $item->getData() + ['product' => $this->productsData[$product->getId()]];
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

        if ($items) {
            // Prepare product data in advance
            $products = array_map(function ($item) {
                return $item->getProduct();
            }, $items);

            $adjustedInfo = $info->fieldNodes[0];
            $this->productsData = $this->productPostProcessor->process(
                $products,
                'items/product',
                $adjustedInfo
            );

            foreach ($items as $item) {
                /** @var QuoteItem $item */
                $product = $item->getProduct();
                $parentIds = $this->configurable->getParentIdsByChild($product->getId());

                if (count($parentIds)) {
                    $parentProduct = $this->productFactory->create()->load(reset($parentIds));
                    $itemsData[] = $this->mergeQuoteItemData($item, $parentProduct);
                } else {
                    $itemsData[] = $this->mergeQuoteItemData($item, $product);
                }
            }
        }

        $address = $cart->isVirtual() ? $cart->getBillingAddress() : $cart->getShippingAddress();
        $tax_amount = $address->getTaxAmount();
        $discount_amount = $address->getDiscountAmount();

        return $cart->getData() +
            [
                'items' => $itemsData,
                'tax_amount' => $tax_amount,
                'discount_amount' => $discount_amount,
                // In interface it is PHPDocumented that it returns bool,
                // while in implementation it returns int.
                'is_virtual' => (bool) $cart->getIsVirtual()
            ];
    }
}
