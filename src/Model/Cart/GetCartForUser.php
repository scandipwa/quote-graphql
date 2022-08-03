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

namespace ScandiPWA\QuoteGraphQl\Model\Cart;

use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser as SourceGetCartForUser;

/**
 * Class GetCartForUser
 * @package ScandiPWA\QuoteGraphQl\Model\Cart
 */
class GetCartForUser extends SourceGetCartForUser
{
    /**
     * Type of error
     */
    public const ERROR_TYPE = 'stock';

    /**
     * Message for out of stock item in cart
     */
    public const ERROR_MESSAGE = 'Some of the products are out of stock.';

    /**
     * Get cart for user
     *
     * @param string $cartHash
     * @param int|null $customerId
     * @param int $storeId
     * @return Quote
     */
    public function execute(string $cartHash, ?int $customerId, int $storeId): Quote
    {
        $cart = parent::execute($cartHash, $customerId, $storeId);

        // For magento, this error is used for notification.
        // But for PWA, this error prevents working with cart data,
        // and out_of_stock status is processed based on product fields, not stock error
        // Therefore, the error is removed if it concerns 'Out of stock item in cart'
        $cart->removeMessageByText(self::ERROR_TYPE, self::ERROR_MESSAGE);

        return $cart;
    }
}
