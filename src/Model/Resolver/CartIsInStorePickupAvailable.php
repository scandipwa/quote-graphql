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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\InventoryInStorePickupShippingApi\Model\IsInStorePickupDeliveryAvailableForCartInterface;

/**
 * Class CartIsInStorePickupAvailable
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class CartIsInStorePickupAvailable implements ResolverInterface
{
    /**
     * @var IsInStorePickupDeliveryAvailableForCartInterface
     */
    protected IsInStorePickupDeliveryAvailableForCartInterface $inStorePickupDeliveryAvailableForCart;

    /**
     * CartIsInStorePickupAvailable constructor.
     * @param ValidationResultFactory $validationResultFactory
     */
    public function __construct(
        IsInStorePickupDeliveryAvailableForCartInterface $inStorePickupDeliveryAvailableForCart
    ) {
        $this->inStorePickupDeliveryAvailableForCart = $inStorePickupDeliveryAvailableForCart;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $cart = $value['model'];

        return $this->inStorePickupDeliveryAvailableForCart->execute((int) $cart->getId());
    }
}
