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


use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;


/**
 * Class RemoveCartItem
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class ApplyCoupon extends CartResolver
{
    /**
     * @var CouponManagementInterface
     */
    protected $couponManagement;

    /**
     * RemoveCartItem constructor.
     * @param ParamOverriderCustomerId $overriderCustomerId
     * @param CartManagementInterface $quoteManagement
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param CouponManagementInterface $couponManagement
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GuestCartRepositoryInterface $guestCartRepository,
        CouponManagementInterface $couponManagement
    )
    {
        parent::__construct($guestCartRepository, $overriderCustomerId, $quoteManagement);
        $this->couponManagement = $couponManagement;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
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
        $couponCode = $args['coupon_code'];

        if (empty($couponCode)) {
            throw new GraphQlInputException(__('Coupon Code can not be empty'));
        }

        $cart = $this->getCart($args);

        if ($cart->getItemsCount() < 1) {
            throw new CartCouponException(__("Cart does not contain products"));
        }

        $cartId = $cart->getId();
        $appliedCouponCode = $this->couponManagement->get($cartId);

        if ($appliedCouponCode !== null) {
            throw new CartCouponException(
                __('A coupon is already applied to the cart. Please remove it to apply another.')
            );
        }

        try {
            $this->couponManagement->set($cartId, $couponCode);
        } catch (NoSuchEntityException | CouldNotSaveException $e) {
            throw new CartCouponException(__('Coupon Code is invalid'), $e);
        }

        return [];
    }
}
