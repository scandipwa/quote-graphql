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


namespace ScandiPWA\QuoteGraphQl\Model\Resolver\Cart;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\QuoteGraphQl\Model\Resolver\CartPrices as SourceCartPrices;

/**
 * @inheritdoc
 */
class CartPrices extends SourceCartPrices
{
    /**
     * @var TotalsCollector
     */
    public $totalsCollector;

    /**
     * @param TotalsCollector $totalsCollector
     */
    public function __construct(TotalsCollector $totalsCollector)
    {
        parent::__construct($totalsCollector);
        $this->totalsCollector = $totalsCollector;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $data = parent::resolve($field, $context, $info, $value, $args);

        /** @var Quote $quote */
        $quote = $value['model'];
        $quote->setCartFixedRules([]);
        $cartTotals = $this->totalsCollector->collectQuoteTotals($quote);
        $currency = $quote->getQuoteCurrencyCode();

        $data['applied_taxes'] = $this->getAppliedTaxes($cartTotals, $currency);
        return $data;
    }

    /**
     * Returns taxes applied to the current quote
     *
     * @param Total $total
     * @param string $currency
     * @return array
     */
    private function getAppliedTaxes(Total $total, string $currency): array
    {
        $appliedTaxesData = [];
        $appliedTaxes = $total->getAppliedTaxes();

        if (empty($appliedTaxes)) {
            return $appliedTaxesData;
        }

        foreach ($appliedTaxes as $appliedTax) {
            $title = "";
            if (!empty($appliedTax['rates'])) {
                $title = $appliedTax['rates'][0]['title'];
            }

            $appliedTaxesData[] = [
                'label' => $appliedTax['id'],
                'title' => $title,
                'percent' => $appliedTax['percent'],
                'amount' => ['value' => $appliedTax['amount'], 'currency' => $currency]
            ];
        }

        return $appliedTaxesData;
    }
}
