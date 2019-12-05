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

namespace ScandiPWA\QuoteGraphQl\Observer;

use Magento\Framework\Event\ObserverInterface;

use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask;
use Magento\Quote\Model\QuoteFactory;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartManagementInterfaceFactory;

/**
 * Class MergeCustomerAndGuestQuotes
 * @package ScandiPWA\QuoteGraphQl\Observer
 */
class MergeCustomerAndGuestQuotes implements ObserverInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var QuoteIdMask
     */
    private $quoteIdMaskResource;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var TokenFactory
     */
    private $tokenFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CartManagementInterfaceFactory
     */
    private $cartManagerFactory;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMask $quoteIdMaskResource
     * @param QuoteFactory $quoteFactory
     * @param TokenFactory $tokenFactory
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMask $quoteIdMaskResource,
        QuoteFactory $quoteFactory,
        TokenFactory $tokenFactory,
        CartRepositoryInterface $cartRepository,
        CartManagementInterfaceFactory $cartManagerFactory
    )
    {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
        $this->quoteFactory = $quoteFactory;
        $this->tokenFactory = $tokenFactory;
        $this->cartRepository = $cartRepository;
        $this->cartManagerFactory = $cartManagerFactory;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $guestQuoteId = $observer->getData('guest_quote_id');
        $customerToken = $observer->getData('customer_token');

        $this->mergeQuotes($guestQuoteId, $customerToken);
    }

    /**
     * Get guest quote by token and merge that with customer quote
     *
     * @param string $guestQuoteId
     * @param string $customerToken
     */
    protected function mergeQuotes(string $guestQuoteId, string $customerToken): void
    {
        $customerId = $this->tokenFactory->create()->loadByToken($customerToken)->getCustomerId();
        $guestQuote = $this->quoteFactory->create()->load(
            $this->getGuestQuoteIdByToken($guestQuoteId)
        );

        try {
            // get existing customer cart
            $customerActiveQuote = $this->cartRepository->getForCustomer($customerId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // create new customer cart
            $cartManager = $this->cartManagerFactory->create();
            $cartManager->createEmptyCartForCustomer($customerId);

            $customerActiveQuote = $cartManager->getCartForCustomer($customerId);
        }

        if ($customerActiveQuote && $guestQuote) {
            // merge carts
            $customerActiveQuote->merge($guestQuote);
            // delete guest cart
            $this->cartRepository->delete($guestQuote);
        }

        // set new cart as active
        $customerActiveQuote->setIsActive(1);
        // save new cart
        $this->cartRepository->save($customerActiveQuote);
    }

    /**
     * Get guest quote id by token
     *
     * @param string $guestQuoteId
     * @return string
     */
    protected function getGuestQuoteIdByToken(string $guestQuoteId): ?string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResource->load($quoteIdMask, $guestQuoteId, 'masked_id');

        return $quoteIdMask->getQuoteId();
    }
}
