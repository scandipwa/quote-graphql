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

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\QuoteGraphQl\Model\Cart\TotalsCollector;
use Magento\QuoteGraphQl\Model\Resolver\CartPrices as SourceCartPrices;

/**
 * Class CartPrices
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class CartPrices extends SourceCartPrices
{
    /**
     * @var TotalsCollector
     */
    protected TotalsCollector $totalsCollector;

    /**
     * CartPrices constructor.
     * @param TotalsCollector $totalsCollector
     */
    public function __construct(
        TotalsCollector $totalsCollector
    ) {
        parent::__construct(
            $totalsCollector
        );

        $this->totalsCollector = $totalsCollector;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $result = parent::resolve($field, $context, $info, $value, $args);

        $quote = $result['model'];

        $applied_rule_ids = $quote->getAppliedRuleIds();
        $coupon_code = $quote->getCouponCode();
        $quote_currency_code = $quote->getQuoteCurrencyCode();

        $cartTotals = $this->totalsCollector->collectQuoteTotals($quote);

        return array_merge($result, [
            'applied_rule_ids' => $applied_rule_ids,
            'applied_taxes' => $this->getAppliedTaxes($cartTotals, $quote_currency_code),
            'coupon_code' => $coupon_code,
            'quote_currency_code' => $quote_currency_code
        ]);
    }

    /**
     * Returns taxes applied to the current quote
     *
     * @param Total $total
     * @param string $currency
     * @return array
     */
    protected function getAppliedTaxes(Total $total, string $currency): array
    {
        $appliedTaxesData = [];
        $appliedTaxes = $total->getAppliedTaxes();

        if (empty($appliedTaxes)) {
            return $appliedTaxesData;
        }

        foreach ($appliedTaxes as $appliedTax) {
            $appliedTaxesData[] = [
                'label' => $appliedTax['id'],
                'amount' => ['value' => $appliedTax['amount'], 'currency' => $currency],
                'percent' => $appliedTax['percent']
            ];
        }

        return $appliedTaxesData;
    }
}
