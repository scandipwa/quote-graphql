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
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;

class GetCartForCustomer implements ResolverInterface
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
     * GetCartForCustomer constructor.
     * @param ParamOverriderCustomerId     $overriderCustomerId
     * @param CartManagementInterface      $quoteManagement
     * @param GuestCartRepositoryInterface $guestCartRepository
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GuestCartRepositoryInterface $guestCartRepository
    )
    {
        $this->quoteManagement = $quoteManagement;
        $this->overriderCustomerId = $overriderCustomerId;
        $this->guestCartRepository = $guestCartRepository;
    }
    
    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field            $field
     * @param ContextInterface $context
     * @param ResolveInfo      $info
     * @param array|null       $value
     * @param array|null       $args
     * @return Value|\Magento\Quote\Api\Data\CartInterface|mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        if (isset($args['guestCartId'])) {
            // At this point we assume this is guest cart
            $cart = $this->guestCartRepository->get($args['guestCartId']);
            $result = $cart->getData();
            $result['id'] = $result['entity_id'];
            
            return $result;
        }
        
        // at this point we assume it is mine cart
        return $this->quoteManagement->getCartForCustomer(
            $this->overriderCustomerId->getOverriddenValue()
        );
    }
}