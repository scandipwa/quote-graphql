<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/quote-graphql
 * @link https://github.com/scandipwa/quote-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\QuoteGraphQl\Model\Resolver\Cart;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\Resolver\Cart as SourceCart;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

/**
 * @inheritdoc
 */
class Cart extends SourceCart
{
    /**
     * @var ParamOverriderCustomerId
     */
    public $overriderCustomerId;

    /**
     * @var CartManagementInterface
     */
    public $quoteManagement;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    public $quoteIdToMaskedQuoteId;

    /**
     * CartResolver constructor.
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GetCartForUser $getCartForUser,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
    ) {
        $this->quoteManagement = $quoteManagement;
        $this->overriderCustomerId = $overriderCustomerId;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        parent::__construct($getCartForUser);
    }

    /**
     * @inheirtDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($args['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        if (empty($args['cart_id'])) {
            $id = $this->getCartForLoggedInUser()->getId();
            $args['cart_id'] = $this->quoteIdToMaskedQuoteId->execute((int)$id);
        }

        return parent::resolve($field, $context, $info, $value, $args);
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    private function getCartForLoggedInUser()
    {
        try {
            return $this->quoteManagement->getCartForCustomer(
                $this->overriderCustomerId->getOverriddenValue()
            );
        } catch (NoSuchEntityException $e) {
            throw new \UnexpectedValueException(__("Unable to retrieve cart. guestCartId is missing or you are not logged in"), 13, $e);
        }
    }
}
