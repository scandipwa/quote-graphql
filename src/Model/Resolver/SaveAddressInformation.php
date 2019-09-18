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

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Api\GuestShippingInformationManagementInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;

/**
 * Class SaveAddressInformation
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class SaveAddressInformation implements ResolverInterface
{
    /**
     * @var ShippingInformationManagement
     */
    protected $shippingInformationManagement;

    /**
     * @var ShippingInformationInterfaceFactory
     */
    protected $shippingInformation;

    /**
     * @var ParamOverriderCartId
     */
    protected $overriderCartId;

    /**
     * @var GuestShippingInformationManagementInterface
     */
    protected $guestShippingInformationManagement;

    /**
     * @var AddressInterfaceFactory
     */
    protected $addressInterfaceFactory;

    public function __construct(
        ShippingInformationManagementInterface $shippingInformationManagement,
        GuestShippingInformationManagementInterface $guestShippingInformationManagement,
        ShippingInformationInterfaceFactory $shippingInformation,
        ParamOverriderCartId $overriderCartId,
        AddressInterfaceFactory $addressInterfaceFactory
    )
    {
        $this->shippingInformation = $shippingInformation;
        $this->overriderCartId = $overriderCartId;
        $this->shippingInformationManagement = $shippingInformationManagement;
        $this->guestShippingInformationManagement = $guestShippingInformationManagement;
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
     * @return mixed|Value
     * @throws \Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        $requestAddressInformation = $args['addressInformation'];

        [
            ShippingInformationInterface::SHIPPING_ADDRESS => $shippingAddress,
            ShippingInformationInterface::BILLING_ADDRESS => $billingAddress,
            ShippingInformationInterface::SHIPPING_CARRIER_CODE => $shippingCarrierCode,
            ShippingInformationInterface::SHIPPING_METHOD_CODE => $shippingMethodCode
        ] = $requestAddressInformation;

        $shippingAddressObject = $this->addressInterfaceFactory->create(['data' => $shippingAddress]);
        $billingAddressObject = $this->addressInterfaceFactory->create(['data' => $billingAddress]);

        $addressInformation = $this->shippingInformation->create([
            'data' => [
                'shipping_address' => $shippingAddressObject,
                'billing_address' => $billingAddressObject,
                'shipping_carrier_code' => $shippingCarrierCode,
                'shipping_method_code' => $shippingMethodCode
            ]
        ]);

        if (isset($args['guestCartId'])) {
            // At this point we assume this is guest cart
            return $this->requestPaymentMethods($addressInformation, $args['guestCartId']);
        }

        return $this->requestPaymentMethods($addressInformation);
    }

    protected function requestPaymentMethods($addressInformation, $guestCartId = null): array
    {
        $paymentInformation = $guestCartId
            ? $this->guestShippingInformationManagement->saveAddressInformation($guestCartId, $addressInformation)
            : $this->shippingInformationManagement->saveAddressInformation(
                $this->overriderCartId->getOverriddenValue(),
                $addressInformation
            );

        return $this->CreatePaymentDetails($paymentInformation);
    }
}
