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

/**
 * Orders data reslover
 */
class OrderListResolver implements ResolverInterface
{
    /**
     * @var CollectionFactoryInterface
     */
    private $collectionFactory;

    /**
     * @var CheckCustomerAccount
     */
    private $checkCustomerAccount;

    /**
     * @param CollectionFactoryInterface $collectionFactory
     * @param CheckCustomerAccount $checkCustomerAccount
     */
    public function __construct(
        CollectionFactoryInterface $collectionFactory,
        CheckCustomerAccount $checkCustomerAccount
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->checkCustomerAccount = $checkCustomerAccount;
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
        $items = [];

        $customerId = $context->getUserId();

        $this->checkCustomerAccount->execute($customerId, $context->getUserType());

        $orders = $this->collectionFactory->create($customerId);

        foreach ($orders as $order) {
            $trackNumbers = [];
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

            $items[] = [
                'base_order_info' => $base_info,
                'shipping_info' => $shippingInfo,
                'payment_info' => $order->getPayment()->getData()
            ];
        }

        return ['items' => $items];
    }
}
