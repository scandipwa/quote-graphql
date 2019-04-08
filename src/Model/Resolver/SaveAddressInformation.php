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

use Magento\Checkout\Model\ShippingInformation;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Checkout\Model\PaymentDetailsFactory;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ShippingAssignmentFactory;
use Magento\Quote\Model\ShippingFactory;
use Magento\Checkout\Model\ShippingInformationManagement;

/**
 * Class SaveAddressInformation
 * @package ScandiPWA\CheckoutGraphQl\Model\Resolver
 */
class SaveAddressInformation implements ResolverInterface {
    /**
     * @var ShippingInformationManagement
     */
    protected $shippingInformationManagement;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var ShippingInformation
     */
    protected $shippingInformation;

    /**
     * SaveAddressInformation constructor.
     * @param ShippingInformationManagement $shippingInformationManagement
     * @param ShippingInformation $shippingInformation
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        ShippingInformationManagement $shippingInformationManagement,
        ShippingInformation $shippingInformation,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->shippingInformation = $shippingInformation;
        $this->shippingInformationManagement = $shippingInformationManagement;
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
        $this->shippingInformation->setData($args['addressInformation']);

        if (isset($args['cartId'])) {
            // At this point we assume this is guest cart
            /** @var QuoteIdMask $quoteIdMask */
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($args['cartId'], 'masked_id');
            return $this->shippingInformationManagement->saveAddressInformation(
                $quoteIdMask->getQuoteId(),
                $this->shippingInformation
            );
        }

        return $this->shippingInformationManagement->saveAddressInformation(
            null,
            $this->shippingInformation
        );
    }
}