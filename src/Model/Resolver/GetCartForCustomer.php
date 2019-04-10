<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/checkout-graphql
 * @link https://github.com/scandipwa/checkout-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\CheckoutGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;

class GetCartForCustomer implements ResolverInterface {
    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var ParamOverriderCustomerId
     */
    protected $overriderCustomerId;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * GetCartForCustomer constructor.
     * @param ParamOverriderCustomerId $overriderCustomerId
     * @param QuoteManagement $quoteManagement
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        QuoteManagement $quoteManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteManagement = $quoteManagement;
        $this->overriderCustomerId = $overriderCustomerId;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return mixed|Value
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (isset($args['cartId'])) {
            // At this point we assume this is guest cart
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($args['cartId'], 'masked_id');
            return $this->quoteManagement->getCartForCustomer($quoteIdMask);
        }

        // at this point we assume it is mine cart
        return $this->quoteManagement->getCartForCustomer(
            $this->overriderCustomerId->getOverriddenValue()
        );
    }
}