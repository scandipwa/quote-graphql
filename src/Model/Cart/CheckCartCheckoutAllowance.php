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
namespace ScandiPWA\QuoteGraphQl\Model\Cart;

use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance as SourceCheckCartCheckoutAllowance;

/**
 * Class CheckCartCheckoutAllowance
 * @package ScandiPWA\QuoteGraphQl\Model\Cart
 */
class CheckCartCheckoutAllowance extends SourceCheckCartCheckoutAllowance
{
    /**
     * @var CheckoutHelper
     */
    protected $checkoutHelper;

    /**
     * CheckCartCheckoutAllowance constructor.
     * @param CheckoutHelper $checkoutHelper
     */
    public function __construct(CheckoutHelper $checkoutHelper)
    {
        $this->checkoutHelper = $checkoutHelper;
        parent::__construct($checkoutHelper);
    }

    /**
     * @param Quote $quote
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException
     */
    public function execute(Quote $quote): void {
        if (false == $quote->getCustomerIsGuest()) {
            return;
        }

        $isAllowedGuestCheckout = (bool)$this->checkoutHelper->isAllowedGuestCheckout($quote);
        if (false === $isAllowedGuestCheckout) {
            throw new GraphQlAuthorizationException(
                __(
                    'Guest checkout is not allowed. ' .
                    'Register a customer account or login with existing one.'
                )
            );
        }
    }
}
