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

namespace ScandiPWA\QuoteGraphQl\Model\Resolver;

use Magento\Checkout\Model\PaymentDetailsFactory;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;
use Magento\Quote\Model\QuoteIdMaskFactory;

/**
 * Class GetPaymentMethods
 *
 * @package ScandiPWA\ServerTime\Model\Resolver
 */
class GetPaymentMethods implements ResolverInterface
{
    /**
     * @var PaymentDetailsFactory
     */
    protected $paymentDetailsFactory;

    /**
     * @var CartTotalRepositoryInterface
     */
    protected $cartTotalsRepository;

    /**
     * @var PaymentMethodManagementInterface
     */
    protected $paymentMethodManagement;

    /**
     * @var ParamOverriderCartId
     */
    protected $overriderCartId;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * GetPaymentMethods constructor.
     * @param PaymentMethodManagementInterface $paymentMethodManagement
     * @param PaymentDetailsFactory $paymentDetailsFactory
     * @param CartTotalRepositoryInterface $cartTotalsRepository
     * @param ParamOverriderCartId $overriderCartId
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        PaymentMethodManagementInterface $paymentMethodManagement,
        PaymentDetailsFactory $paymentDetailsFactory,
        CartTotalRepositoryInterface $cartTotalsRepository,
        ParamOverriderCartId $overriderCartId,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->paymentDetailsFactory = $paymentDetailsFactory;
        $this->paymentMethodManagement = $paymentMethodManagement;
        $this->cartTotalsRepository = $cartTotalsRepository;
        $this->overriderCartId = $overriderCartId;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Get payment methods
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|Value|mixed
     * @throws NoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (isset($args['guestCartId'])) {
            /** @var $quoteIdMask QuoteIdMask */
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($args['guestCartId'], 'masked_id');
            $cartId = $quoteIdMask->getQuoteId();
        } else {
            $cartId = $this->overriderCartId->getOverriddenValue();
        }

        $paymentDetails = $this->paymentDetailsFactory->create();
        $paymentDetails->setPaymentMethods($this->paymentMethodManagement->getList($cartId));
        $paymentDetails->setTotals($this->cartTotalsRepository->get($cartId));

        return array_map(
            function ($payment) {
                /** @var PaymentMethodInterface $payment */
                return [
                    'code' => $payment->getCode(),
                    'title' => $payment->getTitle(),
                ];
            },
            $paymentDetails->getPaymentMethods()
        );
    }
}
