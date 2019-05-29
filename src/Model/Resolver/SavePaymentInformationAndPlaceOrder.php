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
use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;

class SavePaymentInformationAndPlaceOrder implements ResolverInterface {
    /**
     * @var PaymentInformationManagement
     */
    protected $paymentInformationManagement;

    /**
     * @var ParamOverriderCartId
     */
    protected $overriderCartId;

    /**
     * @var GuestPaymentInformationManagementInterface
     */
    protected $guestPaymentInformationManagement;

    /**
     * @var PaymentInterfaceFactory
     */
    protected $paymentInterfaceFactory;

    /**
     * @var AddressInterfaceFactory
     */
    protected $addressInterfaceFactory;

    /**
     * SavePaymentInformationAndPlaceOrder constructor.
     * @param GuestPaymentInformationManagementInterface $guestPaymentInformationManagement
     * @param PaymentInformationManagementInterface $paymentInformationManagement
     * @param ParamOverriderCartId $overriderCartId
     * @param PaymentInterfaceFactory $paymentInterfaceFactory
     * @param AddressInterfaceFactory $addressInterfaceFactory
     */
    public function __construct(
        GuestPaymentInformationManagementInterface $guestPaymentInformationManagement,
        PaymentInformationManagementInterface $paymentInformationManagement,
        ParamOverriderCartId $overriderCartId,
        PaymentInterfaceFactory $paymentInterfaceFactory,
        AddressInterfaceFactory $addressInterfaceFactory
    ) {
        $this->paymentInterfaceFactory = $paymentInterfaceFactory;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->overriderCartId = $overriderCartId;
        $this->addressInterfaceFactory = $addressInterfaceFactory;
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
        [ 'paymentMethod' => $paymentMethod, 'billing_address' => $billingAddress ] = $args['paymentInformation'];

        $paymentMethod = $this->paymentInterfaceFactory->create([ 'data' => $paymentMethod ]);
        $billingAddressObject = $this->addressInterfaceFactory->create([ 'data' => $billingAddress ]);

        $orderId = isset($args['guestCartId'])
            ? $this->guestPaymentInformationManagement->savePaymentInformationAndPlaceOrder(
                $args['guestCartId'],
                $billingAddressObject->getEmail(),
                $paymentMethod,
                $billingAddressObject
            )
            : $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
                $this->overriderCartId->getOverriddenValue(),
                $paymentMethod,
                $billingAddressObject
            );

        return [ 'orderID' => $orderId ];
    }
}