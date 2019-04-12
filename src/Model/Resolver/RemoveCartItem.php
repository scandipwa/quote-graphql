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
use Magento\Quote\Api\GuestCartItemRepositoryInterface;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;

/**
 * Class RemoveCartItem
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class RemoveCartItem implements ResolverInterface
{
    /**
     * @var GuestCartItemRepositoryInterface
     */
    private $guestCartItemRepository;

    /**
     * @var ParamOverriderCartId
     */
    private $overriderCartId;

    /**
     * RemoveCartItem constructor.
     * @param GuestCartItemRepositoryInterface $guestCartItemRepository
     * @param ParamOverriderCartId $overriderCartId
     */
    public function __construct(
        GuestCartItemRepositoryInterface $guestCartItemRepository,
        ParamOverriderCartId $overriderCartId
    )
    {
        $this->overriderCartId = $overriderCartId;
        $this->guestCartItemRepository = $guestCartItemRepository;
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
        ['item_id' => $itemId] = $args;
        $quoteId = $args['quote_id'] ?? $this->overriderCartId->getOverriddenValue();
        return $this->guestCartItemRepository->deleteById($quoteId, $itemId);
    }
}