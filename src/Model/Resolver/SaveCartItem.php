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
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use \Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Quote\Api\GuestCartItemRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

/**
 * Class SaveCartItem
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class SaveCartItem implements ResolverInterface
{
    /**
     * @var CartItemRepositoryInterface
     */
    private $cartItemRepository;
    
    /**
     * @var CartItemInterfaceFactory
     */
    private $cartItemFactory;
    
    /**
     * @var GuestCartItemRepositoryInterface
     */
    private $guestCartItemRepository;
    
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;
    
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    
    /**
     * SaveCartItem constructor.
     * @param CartItemRepositoryInterface      $cartItemRepository
     * @param CartItemInterfaceFactory         $cartItemFactory
     * @param GuestCartItemRepositoryInterface $guestCartItemRepository
     * @param QuoteIdMaskFactory               $quoteIdMaskFactory
     * @param CartRepositoryInterface          $quoteRepository
     */
    public function __construct(
        CartItemRepositoryInterface $cartItemRepository,
        CartItemInterfaceFactory $cartItemFactory,
        GuestCartItemRepositoryInterface $guestCartItemRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $quoteRepository
    )
    {
        $this->cartItemRepository = $cartItemRepository;
        $this->cartItemFactory = $cartItemFactory;
        $this->guestCartItemRepository = $guestCartItemRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteRepository = $quoteRepository;
    }
    
    /**
     * @param Int    $item_id
     * @param String $cartId
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getCartItem(Int $item_id, String $cartId): CartItemInterface
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quote = $this->quoteRepository->getActive($quoteIdMask->getQuoteId());
        
        return $quote->getItemById($item_id);
    }
    
    /**
     * @param array $args
     * @return CartItemInterface
     */
    public function createCartItem(array $args): CartItemInterface
    {
        return $this->cartItemFactory->create(
            [
                'data' => [
                    CartItemInterface::KEY_SKU => $args['sku'],
                    CartItemInterface::KEY_QTY => $args['qty'],
                    CartItemInterface::KEY_QUOTE_ID => $args['quoteId']
                ]
            ]
        );
    }
    
    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface                                $context
     * @param ResolveInfo                                     $info
     * @param array|null                                      $value
     * @param array|null                                      $args
     * @return mixed|Value
     * @throws \Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        ['qty' => $qty] = $args;
        
        if (array_key_exists('item_id', $args)) {
            $item_id = $args['item_id'];
            $cartItem = $this->getCartItem($item_id, $args['quoteId']);
            if ($qty > 0) {
                $cartItem->setQty($qty);
            }
            $result = $this->cartItemRepository->save($cartItem);
        } else {
            $cartItem = $this->createCartItem($args);
            $result = $this->guestCartItemRepository->save($cartItem);
        }
        
        return $result->getData();
    }
}