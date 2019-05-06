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


use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\GuestCartItemRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;

/**
 * Class GetCartItems
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class GetCartItems implements ResolverInterface
{
    /**
     * @var GuestCartItemRepositoryInterface
     */
    private $guestCartItemRepository;
    
    /**
     * @var Configurable
     */
    private $configurable;
    
    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ParamOverriderCartId
     */
    private $overriderCartId;

    /**
     * @var CartItemRepositoryInterface
     */
    private $cartItemRepository;

    /**
     * GetCartItems constructor.
     * @param GuestCartItemRepositoryInterface $guestCartItemRepository
     * @param Configurable $configurable
     * @param ProductFactory $productFactory
     * @param CartItemRepositoryInterface $cartItemRepository
     */
    public function __construct(
        GuestCartItemRepositoryInterface $guestCartItemRepository,
        CartItemRepositoryInterface $cartItemRepository,
        Configurable $configurable,
        ProductFactory $productFactory,
        ParamOverriderCartId $overriderCartId
    )
    {
        $this->cartItemRepository = $cartItemRepository;
        $this->guestCartItemRepository = $guestCartItemRepository;
        $this->configurable = $configurable;
        $this->productFactory = $productFactory;
        $this->overriderCartId = $overriderCartId;
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
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
         if (isset($args['guestCartId'])) {
             $cartItems = $this->guestCartItemRepository->getList($args['guestCartId']);
         } else {
             $cartItems = $this->cartItemRepository->getList($this->overriderCartId->getOverriddenValue());
         }

        if (count($cartItems) < 1) {
            return [];
        }

        $result = [];

        foreach ($cartItems as $cartItem) {
            $currentProduct = $cartItem->getProduct();
            $parentIds = $this->configurable->getParentIdsByChild($currentProduct->getId());
            if (count($parentIds)) {
                $parentProduct = $this->productFactory->create()->load(reset($parentIds));
                $result[] = array_merge($cartItem->getData(),
                    ['product' =>
                        array_merge(
                            $parentProduct->getData(),
                            ['model' => $parentProduct]
                        )
                    ]
                );
            } else {
                $result[] = array_merge($cartItem->getData(),
                    ['product' =>
                        array_merge(
                            $currentProduct->getData(),
                            ['model' => $currentProduct]
                        )
                    ]
                );
            }
        }

        return $result;
    }
}