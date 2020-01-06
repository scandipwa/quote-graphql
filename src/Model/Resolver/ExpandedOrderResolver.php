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
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;
use ScandiPWA\QuoteGraphQl\Model\Customer\CheckCustomerAccount;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Sales\Model\OrderRepository;

/**
 * Orders data resolver
 */
class ExpandedOrderResolver implements ResolverInterface
{
    /**
     * @var CollectionFactoryInterface
     */
    protected $collectionFactory;

    /**
     * @var CheckCustomerAccount
     */
    protected $checkCustomerAccount;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @param CollectionFactoryInterface $collectionFactory
     * @param CheckCustomerAccount $checkCustomerAccount
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        CollectionFactoryInterface $collectionFactory,
        CheckCustomerAccount $checkCustomerAccount,
        OrderRepository $orderRepository
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->checkCustomerAccount = $checkCustomerAccount;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $itemsData = [];
        $trackNumbers = [];

        $customerId = $context->getUserId();
        $this->checkCustomerAccount->execute($customerId, $context->getUserType());

        $orderId = $args['id'];
        $order = $this->orderRepository->get($orderId);

        if ($customerId != $order->getCustomerId()) {
            throw new GraphQlNoSuchEntityException(__('Customer ID is invalid.'));
        }

        foreach ($order->getAllVisibleItems() as $item) {
            $itemsData[] = $item;
        }

        $tracksCollection = $order->getTracksCollection();

        foreach ($tracksCollection->getItems() as $track) {
            $trackNumbers[] = $track->getTrackNumber();
        }

        $shippingInfo = [
            'shipping_amount' => $order->getShippingAmount(),
            'shipping_method' => $order->getShippingMethod(),
            'shipping_address' => $order->getShippingAddress(),
            'shipping_description' => $order->getShippingDescription(),
            'tracking_numbers' => $trackNumbers
        ];

        $base_info = [
            'id' => $order->getId(),
            'increment_id' => $order->getIncrementId(),
            'created_at' => $order->getCreatedAt(),
            'grand_total' => $order->getGrandTotal(),
            'sub_total' => $order->getBaseSubtotalInclTax(),
            'status' => $order->getStatus(),
            'status_label' => $order->getStatusLabel(),
            'total_qty_ordered' => $order->getTotalQtyOrdered(),
        ];

        return [
            'base_order_info' => $base_info,
            'shipping_info' => $shippingInfo,
            'payment_info' => $order->getPayment()->getData(),
            'products' => $itemsData
        ];
    }
}
