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
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use \Magento\Quote\Api\Data\PaymentMethodInterface;
use \Magento\Quote\Api\Data\TotalsItemInterface;

/**
 * Class SaveAddressInformation
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class SaveAddressInformation implements ResolverInterface {
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
    ) {
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
        $requestAddressInformation = $args['addressInformation'];

        [
            ShippingInformationInterface::SHIPPING_ADDRESS => $shippingAddress,
            ShippingInformationInterface::BILLING_ADDRESS => $billingAddress,
            ShippingInformationInterface::SHIPPING_CARRIER_CODE => $shippingCarrierCode,
            ShippingInformationInterface::SHIPPING_METHOD_CODE => $shippingMethodCode
        ] = $requestAddressInformation;

        $shippingAddressObject = $this->addressInterfaceFactory->create([ 'data' => $shippingAddress ]);
        $billingAddressObject = $this->addressInterfaceFactory->create([ 'data' => $billingAddress ]);

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
        $rawPaymentInformation = $guestCartId
            ? $this->guestShippingInformationManagement->saveAddressInformation($guestCartId, $addressInformation)
            : $this->shippingInformationManagement->saveAddressInformation(
                $this->overriderCartId->getOverriddenValue(),
                $addressInformation
            );

        $rawTotals = $rawPaymentInformation->getTotals();

        // The following crutch exists because of Magento not being able to fix an issue for four years
        // Link to the issue: https://github.com/magento/magento2/issues/7769
        $rawTotals->setGrandTotal($rawTotals->getTotalSegments()['grand_total']['value']);

        return [
            'payment_methods' => array_map(
                function ($payment) {
                    /** @var PaymentMethodInterface $payment */
                    return [
                        'code' => $payment->getCode(),
                        'title' => $payment->getTitle(),
                    ];
                },
                $rawPaymentInformation->getPaymentMethods()
            ),
            'totals' => array_merge(
                $rawTotals->getData(),
                [ 'items' => array_map(function ($item) {
                    /** @var TotalsItemInterface $item */
                    return [
                        TotalsItemInterface::KEY_ITEM_ID => $item->getItemId(),
                        TotalsItemInterface::KEY_PRICE => $item->getPrice(),
                        TotalsItemInterface::KEY_BASE_PRICE => $item->getBasePrice(),
                        TotalsItemInterface::KEY_QTY => $item->getQty(),
                        TotalsItemInterface::KEY_ROW_TOTAL => $item->getRowTotal(),
                        TotalsItemInterface::KEY_BASE_ROW_TOTAL => $item->getBaseRowTotal(),
                        TotalsItemInterface::KEY_ROW_TOTAL_WITH_DISCOUNT => $item->getRowTotalWithDiscount(),
                        TotalsItemInterface::KEY_TAX_AMOUNT => $item->getTaxAmount(),
                        TotalsItemInterface::KEY_BASE_TAX_AMOUNT => $item->getBaseTaxAmount(),
                        TotalsItemInterface::KEY_TAX_PERCENT => $item->getTaxPercent(),
                        TotalsItemInterface::KEY_DISCOUNT_AMOUNT => $item->getDiscountAmount(),
                        TotalsItemInterface::KEY_BASE_DISCOUNT_AMOUNT => $item->getBaseDiscountAmount(),
                        TotalsItemInterface::KEY_DISCOUNT_PERCENT => $item->getDiscountPercent(),
                        TotalsItemInterface::KEY_PRICE_INCL_TAX => $item->getPriceInclTax(),
                        TotalsItemInterface::KEY_BASE_PRICE_INCL_TAX => $item->getBasePriceInclTax(),
                        TotalsItemInterface::KEY_ROW_TOTAL_INCL_TAX => $item->getRowTotalInclTax(),
                        TotalsItemInterface::KEY_BASE_ROW_TOTAL_INCL_TAX => $item->getBaseRowTotalInclTax(),
                        TotalsItemInterface::KEY_OPTIONS => $item->getOptions(),
                        TotalsItemInterface::KEY_WEEE_TAX_APPLIED_AMOUNT => $item->getWeeeTaxAppliedAmount(),
                        TotalsItemInterface::KEY_WEEE_TAX_APPLIED => $item->getWeeeTaxApplied(),
                        TotalsItemInterface::KEY_NAME => $item->getName(),
                    ];
                }, $rawTotals->getItems()) ]
            )
        ];
    }
}
