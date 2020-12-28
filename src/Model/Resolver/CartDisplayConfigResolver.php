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
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Tax\Model\Config;
use Magento\Store\Model\StoreManagerInterface;

class CartDisplayConfigResolver implements ResolverInterface {
    const DISPLAY_CART_TAX_IN_PRICE_INCL_TAX = 'DISPLAY_CART_TAX_IN_PRICE_INCL_TAX';
    const DISPLAY_CART_TAX_IN_PRICE_EXL_TAX = 'DISPLAY_CART_TAX_IN_PRICE_EXL_TAX';
    const DISPLAY_CART_TAX_IN_PRICE_BOTH = 'DISPLAY_CART_TAX_IN_PRICE_BOTH';

    const DISPLAY_CART_TAX_IN_SUBTOTAL_INCL_TAX = 'DISPLAY_CART_TAX_IN_SUBTOTAL_INCL_TAX';
    const DISPLAY_CART_TAX_IN_SUBTOTAL_EXL_TAX = 'DISPLAY_CART_TAX_IN_SUBTOTAL_EXL_TAX';
    const DISPLAY_CART_TAX_IN_SUBTOTAL_BOTH = 'DISPLAY_CART_TAX_IN_SUBTOTAL_BOTH';

    const DISPLAY_CART_TAX_IN_SHIPPING_INCL_TAX = 'DISPLAY_CART_TAX_IN_SHIPPING_INCL_TAX';
    const DISPLAY_CART_TAX_IN_SHIPPING_EXL_TAX = 'DISPLAY_CART_TAX_IN_SHIPPING_EXL_TAX';
    const DISPLAY_CART_TAX_IN_SHIPPING_BOTH = 'DISPLAY_CART_TAX_IN_SHIPPING_BOTH';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * CartDisplayConfigResolver constructor.
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager
    )
    {
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|Value|mixed
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        $store = (int)$this->storeManager->getStore()->getId();

        return [
            'display_tax_in_price' => $this->getDisplayTaxInPriceValue($store),
            'display_tax_in_subtotal' => $this->getDisplayTaxInSubtotalValue($store),
            'display_tax_in_shipping_amount' => $this->getDisplayTaxInShippingAmountValue($store),
            'include_tax_in_order_total' => $this->getIncludeTaxInOrderTotalValue($store),
            'display_full_tax_summary' => $this->getDisplayFullTaxSummaryValue($store),
            'display_zero_tax_subtotal' => $this->getDisplayZeroTaxSubtotalValue($store)
        ];
    }

    /**
     * @param int $store
     * @return string
     */
    private function getDisplayTaxInPriceValue(int $store)
    {
        $result = self::DISPLAY_CART_TAX_IN_PRICE_BOTH;

        if ($this->config->displayCartPricesInclTax($store)) {
            $result = self::DISPLAY_CART_TAX_IN_PRICE_INCL_TAX;
        } elseif ($this->config->displayCartPricesExclTax($store)) {
            $result = self::DISPLAY_CART_TAX_IN_PRICE_EXL_TAX;
        }

        return $result;
    }

    /**
     * @param int $store
     * @return string
     */
    private function getDisplayTaxInSubtotalValue(int $store)
    {
        $result = self::DISPLAY_CART_TAX_IN_SUBTOTAL_BOTH;

        if ($this->config->displayCartSubtotalInclTax($store)) {
            $result = self::DISPLAY_CART_TAX_IN_SUBTOTAL_INCL_TAX;
        } elseif ($this->config->displayCartSubtotalExclTax($store)) {
            $result = self::DISPLAY_CART_TAX_IN_SUBTOTAL_EXL_TAX;
        }

        return $result;
    }

    private function getDisplayTaxInShippingAmountValue(int $store)
    {
        $result = self::DISPLAY_CART_TAX_IN_SHIPPING_BOTH;

        if ($this->config->displayCartShippingInclTax($store)) {
            $result = self::DISPLAY_CART_TAX_IN_SHIPPING_INCL_TAX;
        } elseif ($this->config->displayCartShippingExclTax($store)) {
            $result = self::DISPLAY_CART_TAX_IN_SHIPPING_EXL_TAX;
        }

        return $result;
    }

    private function getIncludeTaxInOrderTotalValue(int $store)
    {
        return $this->config->displayCartTaxWithGrandTotal($store);
    }

    private function getDisplayFullTaxSummaryValue(int $store)
    {
        return $this->config->displayCartFullSummary($store);
    }

    private function getDisplayZeroTaxSubtotalValue(int $store)
    {
        return $this->config->displayCartZeroTax($store);
    }
}
