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
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;


/**
 * Class RemoveCartItem
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class ApplyCoupon implements ResolverInterface
{
    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var ParamOverriderCustomerId
     */
    protected $overriderCustomerId;

    /**
     * @var GuestCartRepositoryInterface
     */
    protected $guestCartRepository;

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
        $this->quoteManagement = $quoteManagement;
        $this->overriderCustomerId = $overriderCustomerId;
        $this->guestCartRepository = $guestCartRepository;
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
            throw new GraphQlInputException(__('Required parameter "coupon_code" is missing'));
        }

        if (isset($args['guestCartId'])) {
            // At this point we assume this is guest cart
            $cart = $this->guestCartRepository->get($args['guestCartId']);
        } else {
            // at this point we assume it is mine cart
            $cart = $this->quoteManagement->getCartForCustomer(
                $this->overriderCustomerId->getOverriddenValue()
            );
        }

        $cartId = $cart->getId();

        /* Check current cart does not have coupon code applied */
        $appliedCouponCode = $this->couponManagement->get($cartId);
        if (!empty($appliedCouponCode)) {
            throw new GraphQlInputException(
                __('A coupon is already applied to the cart. Please remove it to apply another')
            );
        }
        try {
            $this->couponManagement->set($cartId, $couponCode);
        } catch (NoSuchEntityException $e) {
            $message = $e->getMessage();
            if (preg_match('/The "\d+" Cart doesn\'t contain products/', $message)) {
                $message = 'Cart does not contain products.';
            }
            throw new GraphQlNoSuchEntityException(__($message), $e);
        } catch (CouldNotSaveException $e) {
            throw new LocalizedException(__($e->getMessage()), $e);
        }

        return [];
    }
}