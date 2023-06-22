<?php

/**
 * @category CAG Ateles
 * @package Ateles\DogmanPWA\QuoteGraphQL\Model;
 * @author Dzemal Becirevic <dzemal.becirevic@cag.se> & Andrii Antoniuk <andrii.antoniuk@cag.se>
 * @copyright Copyright (c) 2023 CAG Ateles AB
 * @license http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */

namespace Ateles\DogmanPWA\QuoteGraphQL\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser as SourceGetCartForUser;

class GetCartForUser extends SourceGetCartForUser
{
    /**
     * List of error types and messages that has to be
     * translated and removed
     */
    protected const STOCK_ERRORS_TO_REMOVE = [
        'stock' => ['Some of the products are out of stock.'],
        'qty' => [
            'There are no source items with the in stock status',
            'This product(s) is out of stock.',
            'This product is out of stock.'
        ]
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

        /* TIC-2306-0118
         * ScandiPWA does by default try to remove stock errors from cart
         * but since they do not manage translated error messages the code
         * can not remove messages in for example Swedish (sv_SE).
         *
         * We also need to remove `qty` stock messages as well since SPWA handles
         * stock messages from the product.
         */
        foreach (self::STOCK_ERRORS_TO_REMOVE as $type => $messages) {
            foreach ($messages as $message) {
                $cart->removeMessageByText($type, __($message));
            }
        }

        return $cart;
    }
}
