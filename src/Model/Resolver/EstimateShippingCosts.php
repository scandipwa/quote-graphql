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

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\Data\EstimateAddressInterfaceFactory;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\ShippingMethodManagement;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class EstimateShippingCosts
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class EstimateShippingCosts implements ResolverInterface {
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var ShippingMethodManagement
     */
    protected $shippingMethodManagement;

    /**
     * @var ParamOverriderCartId
     */
    protected $overriderCartId;

    /**
     * @var EstimateAddressInterfaceFactory
     */
    protected $addressInterfaceFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * EstimateShippingCosts constructor.
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param ShippingMethodManagement $shippingMethodManagement
     * @param ParamOverriderCartId $overriderCartId
     * @param EstimateAddressInterfaceFactory $addressInterfaceFactory
     * @param StoreManagerInterface $storeManager
     * @param QuoteRepository $quoteRepository,
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        ShippingMethodManagement $shippingMethodManagement,
        ParamOverriderCartId $overriderCartId,
        EstimateAddressInterfaceFactory $addressInterfaceFactory,
        StoreManagerInterface $storeManager,
        QuoteRepository $quoteRepository
    ) {
        $this->addressInterfaceFactory = $addressInterfaceFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->overriderCartId = $overriderCartId;
        $this->storeManager = $storeManager;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Changes Quote store ID to the current store ID:
     * - When switching between stores to capture correct
     * - shipping methods.
     *
     * @param $quoteId
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function updateStoreId($cartId)
    {
        $quote = $this->quoteRepository->getActive($cartId);
        // Though it should return int it actually returns string
        $storeId = $this->storeManager->getStore()->getId();
        if ($storeId == $quote->getStoreId()) {
            return;
        }

        $quote->setStoreId($storeId);
        $this->quoteRepository->save($quote);
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
        $shippingAddressObject = $this->addressInterfaceFactory->create([ 'data' => $args['address'] ]);

        $cartId = isset($args['guestCartId'])
            ? $this->quoteIdMaskFactory->create()->load($args['guestCartId'], 'masked_id')->getQuoteId()
            : $this->overriderCartId->getOverriddenValue();

        $this->updateStoreId($cartId);
        $shippingMethods = $this->shippingMethodManagement->estimateByAddress($cartId, $shippingAddressObject);

        return array_map(function($shippingMethod) {
            /** @var ShippingMethodInterface $shippingMethod */
            return [
                'carrier_code' => $shippingMethod->getCarrierCode(),
                'method_code' => $shippingMethod->getMethodCode(),
                'carrier_title' => $shippingMethod->getCarrierTitle(),
                'method_title' => $shippingMethod->getMethodTitle(),
                'error_message' => $shippingMethod->getErrorMessage(),
                'amount' => $shippingMethod->getAmount(),
                'base_amount' => $shippingMethod->getBaseAmount(),
                'price_excl_tax' => $shippingMethod->getPriceExclTax(),
                'price_incl_tax' => $shippingMethod->getPriceInclTax(),
                'available' => $shippingMethod->getAvailable()
            ];
        }, $shippingMethods);
    }
}

