<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/checkout-graphql
 * @link https://github.com/scandipwa/checkout-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\CheckoutGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\ShippingMethodManagement;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;

/**
 * Class EstimateShippingCosts
 * @package ScandiPWA\CheckoutGraphQl\Model\Resolver
 */
class EstimateShippingCosts implements ResolverInterface {
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var ShippingMethodManagement
     */
    protected $shippingMethodManagement;

    /**
     * @var ParamOverriderCartId
     */
    protected $overriderCartId;

    /**
     * EstimateShippingCosts constructor.
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param ShippingMethodManagement $shippingMethodManagement
     * @param ParamOverriderCartId $overriderCartId
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        ShippingMethodManagement $shippingMethodManagement,
        ParamOverriderCartId $overriderCartId
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->overriderCartId = $overriderCartId;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return mixed|Value
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $address = $args['address'];

        if (isset($args['cartId'])) {
            // At this point we assume this is guest cart
            /** @var QuoteIdMask $quoteIdMask */
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($args['cartId'], 'masked_id');
            return $this->shippingMethodManagement->estimateByAddress(
                $quoteIdMask->getQuoteId(),
                $address
            );
        }

        // at this point we assume it is mine cart
        return $this->shippingMethodManagement->estimateByAddress(
            $this->overriderCartId->getOverriddenValue(),
            $address
        );
    }
}