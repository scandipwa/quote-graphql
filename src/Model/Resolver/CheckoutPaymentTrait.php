<?php


namespace ScandiPWA\QuoteGraphQl\Model\Resolver;


use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Quote\Api\Data\TotalsItemInterface;

trait CheckoutPaymentTrait
{
    function CreatePaymentDetails(PaymentDetailsInterface $paymentInformation)
    {
        $totals = $paymentInformation->getTotals();

        return [
            'payment_methods' => array_map(
                function ($payment) {
                    /** @var PaymentMethodInterface $payment */
                    return [
                        'code' => $payment->getCode(),
                        'title' => $payment->getTitle(),
                    ];
                },
                $paymentInformation->getPaymentMethods()
            ),
            'totals' => array_merge(
                $totals->getData(),
                [
                    'items_qty' => $totals->getItemsQty(),
                    'items' => array_map(function ($item) {
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
                    }, $totals->getItems())]
            )
        ];
    }
}
