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

use Magento\CatalogInventory\Helper\Data;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote\Item;
use Magento\CatalogInventory\Api\StockRegistryInterface;

/**
 * Resolves cart items status - out of stock, incorrect qty, ok
 */
class CartItemStatus implements ResolverInterface
{
    public const ERR_QRY = 'ERR_QTY';
    public const MSG_OK = 'STATUS_OK';

    /**
     * @var StockRegistryInterface
     */
    public $stockRegistry;

    /**
     * @param StockRegistryInterface $stockRegistry
     */
    public function __construct(StockRegistryInterface $stockRegistry)
    {
        $this->stockRegistry = $stockRegistry;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var Item $cartItem */
        $cartItem = $value['model'];

        $product = $cartItem->getProduct();
        /* @var \Magento\CatalogInventory\Api\Data\StockStatusInterface $stockStatus */
        $stockStatus = $this->stockRegistry->getStockStatus($product->getId(), $product->getStore()->getWebsiteId());

        /* @var \Magento\CatalogInventory\Api\Data\StockStatusInterface $parentStockStatus */
        $parentStockStatus = false;

        /**
         * Check if product in stock. For composite products check base (parent) item stock status
         */
        if ($cartItem->getParentItem()) {
            $product = $cartItem->getParentItem()->getProduct();
            $parentStockStatus = $this->stockRegistry->getStockStatus(
                $product->getId(),
                $product->getStore()->getWebsiteId()
            );
        }

        if ($stockStatus) {
            if ($stockStatus->getStockStatus() === Stock::STOCK_OUT_OF_STOCK
                || $parentStockStatus && $parentStockStatus->getStockStatus() == Stock::STOCK_OUT_OF_STOCK
            ) {
                $hasError = $cartItem->getStockStateResult()
                    ? $cartItem->getStockStateResult()->getHasError() : false;

                if (!$hasError) {
                    return Stock::STOCK_OUT_OF_STOCK;
                } else {
                    return self::ERR_QRY;
                }
            }
        } else {
            return Stock::STOCK_OUT_OF_STOCK;
        }

        return self::MSG_OK;
    }
}
