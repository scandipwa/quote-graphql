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
        $result = parent::resolve($field, $context, $info, $value, $args);

        /** @var Address $address */
        $address = $value['model'];

        return array_merge($result, [
            'address' => [
                'city' => $address->getCity(),
                'country' => [
                    'code' => $address->getCountryId()
                ],
                'email' => $address->getEmail(),
                'firstname' => $address->getFirstname(),
                'lastname' => $address->getLastname(),
                'postcode' => $address->getPostcode(),
                'region' => [
                    'label' => $address->getRegion()
                ],
                'street' => $address->getStreet(),
                'telephone' => $address->getTelephone(),
                'vat_id' => $address->getVatId()
            ],
            'amount_incl_tax' => [
                'value' => $address->getShippingInclTax(),
                'currency' => $address->getQuote()->getQuoteCurrencyCode(),
            ],
            'tax_amount' => $address->getShippingTaxAmount()
        ]);
    }
}
