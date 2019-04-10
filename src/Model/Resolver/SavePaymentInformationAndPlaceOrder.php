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

use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;

class SavePaymentInformationAndPlaceOrder implements ResolverInterface {
    /**
     * @var PaymentInformationManagement
     */
    protected $paymentInformationManagement;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var PaymentInterface
     */
    protected $payment;

    /**
     * @var AddressInterface
     */
    protected $address;

    /**
     * @var ParamOverriderCartId
     */
    protected $overriderCartId;

    /**
     * SavePaymentInformationAndPlaceOrder constructor.
     * @param PaymentInformationManagement $paymentInformationManagement
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param PaymentInterface $payment
     * @param AddressInterface $address
     * @param ParamOverriderCartId $overriderCartId
     */
    public function __construct(
        PaymentInformationManagement $paymentInformationManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        PaymentInterface $payment,
        AddressInterface $address,
        ParamOverriderCartId $overriderCartId
    ) {
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->overriderCartId = $overriderCartId;
        $this->payment = $payment;
        $this->address = $address;
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
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $paymentMethod = $this->payment->setData($args['paymentMethod']);
        $billingAddress = $this->address->setData($args['billing_address']);

        if (isset($args['cartId'])) {
            // At this point we assume this is guest cart
            /** @var QuoteIdMask $quoteIdMask */
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($args['cartId'], 'masked_id');
            return $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
                $quoteIdMask->getQuoteId(),
                $paymentMethod,
                $billingAddress
            );
        }

        return $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
            $this->overriderCartId->getOverriddenValue(),
            $paymentMethod,
            $billingAddress
        );
    }
}