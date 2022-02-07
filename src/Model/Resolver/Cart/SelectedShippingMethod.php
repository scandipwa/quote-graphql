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

namespace ScandiPWA\QuoteGraphQl\Model\Resolver\Cart;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Resolver\ShippingAddress\SelectedShippingMethod as SourceSelectedShippingMethod;
use Magento\Quote\Model\Quote\Address;

/**
 * @inheritdoc
 */
class SelectedShippingMethod extends SourceSelectedShippingMethod
{
    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $data = parent::resolve($field, $context, $info, $value, $args);

        /** @var Address $address */
        $address = $value['model'];

        $data['amount_with_tax'] = [
            'value' => $address->getShippingInclTax(),
            'currency' => $address->getQuote()->getQuoteCurrencyCode(),
        ];

        return $data;
    }
}
