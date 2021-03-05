<?php

namespace ScandiPWA\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Quote\Model\QuoteIdMask;

/**
 * Class LinkOrder
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class LinkOrder implements ResolverInterface
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $session;

    /**
     * LinkOrder constructor.
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->session = $customerSession;
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
        if (!isset($args['order_id']) || !isset($args['customer_id'])) {
            return false;
        }

        $incrementId = $args['order_id'];
        $customerId = $args['customer_id'];

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $incrementId, 'eq')->create();
        $order = $this->orderRepository->getList($searchCriteria)->getFirstItem();

        // If order is un-siggned
        if ($order->getId() && !$order->getCustomerId()) {
            $order->setCustomerId($customerId);
            $order->setCustomerIsGuest(0);
            $this->orderRepository->save($order);
            return true;
        }

        return false;
    }
}
