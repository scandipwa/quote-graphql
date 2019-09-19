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

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;

/**
 * Class SaveAddressInformation
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class GetPaymentInformation extends CartResolver
{
    use CheckoutPaymentTrait;

    /**
     * @var PaymentInformationManagementInterface
     */
    protected $paymentInformationManagement;

    /**
     * @var GuestPaymentInformationManagementInterface
     */
    protected $guestPaymentInformationManagement;

    public function __construct(
        PaymentInformationManagementInterface $paymentInformationManagement,
        GuestPaymentInformationManagementInterface $guestPaymentInformationManagement,
        GuestCartRepositoryInterface $guestCartRepository,
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement
    )
    {
        parent::__construct($guestCartRepository, $overriderCustomerId, $quoteManagement);

        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|Value|mixed
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        $cartId = $this->getCartId($args);

        $paymentInformation = $this->isGuest($args)
            ? $this->guestPaymentInformationManagement->getPaymentInformation($cartId)
            : $this->paymentInformationManagement->getPaymentInformation($cartId);

        return $this->CreatePaymentDetails($paymentInformation);
    }
}
