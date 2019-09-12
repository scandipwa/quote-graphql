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

use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Catalog\Model\ProductFactory;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteManagement;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;

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
     * @var TotalsCollector
     */
    protected $totalsCollector;

    /**
     * GetCartForCustomer constructor.
     * @param ParamOverriderCustomerId $overriderCustomerId
     * @param CartManagementInterface $quoteManagement
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param Configurable $configurable
     * @param ProductFactory $productFactory
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GuestCartRepositoryInterface $guestCartRepository,
        Configurable $configurable,
        ProductFactory $productFactory,
        TotalsCollector $totalsCollector

    )
    {
        parent::__construct($guestCartRepository, $overriderCustomerId, $quoteManagement);
        $this->configurable = $configurable;
        $this->productFactory = $productFactory;
        $this->totalsCollector = $totalsCollector;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return Value|\Magento\Quote\Api\Data\CartInterface|mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        $cart = $this->getCart($args['guestCartId']);
        $cartId = $cart->getId();

        if($cartId === null){
            throw new \Exception("Cart could not be found");
        }

        $itemsData = [];

        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();
            $parentIds = $this->configurable->getParentIdsByChild($product->getId());
            if (count($parentIds)) {
                $parentProduct = $this->productFactory->create()->load(reset($parentIds));
                $itemsData[] = array_merge(
                    $item->getData(),
                    ['product' =>
                        array_merge(
                            $parentProduct->getData(),
                            ['model' => $parentProduct]
                        )
                    ]
                );
            } else {
                $itemsData[] = array_merge(
                    $item->getData(),
                    ['product' =>
                        array_merge(
                            $product->getData(),
                            ['model' => $product]
                        )
                    ]
                );
            }
        }

        $address = $cart->isVirtual() ? $cart->getBillingAddress() : $cart->getShippingAddress();
        $tax_amount = $address->getTaxAmount();
        $cartTotals = $this->totalsCollector->collectQuoteTotals($cart);
        $discount_amount = $cartTotals->getDiscountAmount();

        $data = array_merge(
            $cart->getData(),
            [
                'items' => $itemsData,
                'tax_amount' => $tax_amount,
                'discount_amount' => $discount_amount
            ]
        );

        return $data;
    }
}