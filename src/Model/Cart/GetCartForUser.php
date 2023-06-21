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
     * List of error types and messages that has to be
     * translated and removed
     */
    protected const STOCK_ERRORS_TO_REMOVE = [
        'stock' => 'Some of the products are out of stock.',
        'qty' => 'There are no source items with the in stock status'
    ];

    /**
     * Get cart for user
     *
     * @param string $cartHash
     * @param int|null $customerId
     * @param int $storeId
     * @return Quote
     * @throws NoSuchEntityException
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */

    public function execute(string $cartHash, ?int $customerId, int $storeId): Quote
    {
        $cart = parent::execute($cartHash, $customerId, $storeId);
        /* For Magento, those errors are used for notification.
         But for PWA, those errors prevent working with cart data,
         and out_of_stock status is processed based on product fields, not stock error
         Therefore, the error is removed if it concerns 'Out of stock item in cart'
         */ 

        foreach (self::STOCK_ERRORS_TO_REMOVE as $type => $message) {
            $cart->removeMessageByText($type, __($message));
        }

        return $cart;
    }
}
