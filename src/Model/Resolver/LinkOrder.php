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
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Customer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;

/**
 * Class LinkOrder
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class LinkOrder implements ResolverInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * LinkOrder constructor.
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        CustomerFactory $customerFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->customerFactory = $customerFactory;
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
        if (!isset($args['customer_email'])) {
            return false;
        }

        $customerEmail = $args['customer_email'];
        $customer = $this->getCustomerByEmail($customerEmail);
        if (!$customer->getId()) {
            return false;
        }

        // Loads last order from session
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$this->validateOrder($order, $customer)) {
            return false;
        }

        $order->setCustomerId($customer->getId());
        $order->setCustomerIsGuest(0);
        $this->orderRepository->save($order);

        return true;
    }

    /**
     * @param Order $order
     * @param Customer $custumer
     * @return bool
     */
    public function validateOrder($order, $custumer) : bool {
        // If order is un-siggned
        if (!$order->getId() || $order->getCustomerId()) {
            return false;
        }

        // If order was placed in same store
        if ($order->getStoreId() !== $custumer->getStoreId()) {
            return false;
        }

        return true;
    }

    /**
     * @param $email
     * @return Customer
     * @throws NoSuchEntityException
     */
    public function getCustomerByEmail($email)
    {
        $websiteID = $this->storeManager->getStore()->getWebsiteId();
        $customer = $this->customerFactory->create()->setWebsiteId($websiteID)->loadByEmail($email);

        return $customer;
    }
}
