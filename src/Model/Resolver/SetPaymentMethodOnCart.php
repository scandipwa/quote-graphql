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

use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\Cart\SetPaymentMethodOnCart as SetPaymentMethodOnCartModel;

/**
 * Mutation resolver for setting payment method for shopping cart
 */
class SetPaymentMethodOnCart implements ResolverInterface
{
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var SetPaymentMethodOnCartModel
     */
    private $setPaymentMethodOnCart;

    /**
     * @var CheckCartCheckoutAllowance
     */
    private $checkCartCheckoutAllowance;

    /**
     * @param GetCartForUser $getCartForUser
     * @param SetPaymentMethodOnCartModel $setPaymentMethodOnCart
     * @param CheckCartCheckoutAllowance $checkCartCheckoutAllowance
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        CartManagementInterface $cartManagement,
        SetPaymentMethodOnCartModel $setPaymentMethodOnCart,
        CheckCartCheckoutAllowance $checkCartCheckoutAllowance
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->cartManagement = $cartManagement;
        $this->setPaymentMethodOnCart = $setPaymentMethodOnCart;
        $this->checkCartCheckoutAllowance = $checkCartCheckoutAllowance;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $guestCartId = $args['input']['guest_cart_id'] ?? '';

        if (empty($args['input']['payment_method']['code'])) {
            throw new GraphQlInputException(__('Required parameter "code" for "payment_method" is missing.'));
        }
        $paymentData = $args['input']['payment_method'];

        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        $customerId = $context->getUserId();
        if ($guestCartId !== '') {
            $cart = $this->getCartForUser->execute($guestCartId, $customerId, $storeId);
        } else {
            $cart = $this->cartManagement->getCartForCustomer($customerId);
        }

        $this->checkCartCheckoutAllowance->execute($cart);
        $this->setPaymentMethodOnCart->execute($cart, $paymentData);

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }
}
