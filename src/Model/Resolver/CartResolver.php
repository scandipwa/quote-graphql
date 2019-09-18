<?php


namespace ScandiPWA\QuoteGraphQl\Model\Resolver;


use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Quote\Api\Data\TotalsItemInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;
use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use phpDocumentor\Reflection\Types\Boolean;

abstract class CartResolver implements ResolverInterface
{
    /**
     * CartResolver constructor.
     */
    public function __construct(
        GuestCartRepositoryInterface $guestCartRepository,
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement
    )
    {
        $this->quoteManagement = $quoteManagement;
        $this->overriderCustomerId = $overriderCustomerId;
        $this->guestCartRepository = $guestCartRepository;
    }

    /**
     * @param string|null $guestCartId
     * @return CartInterface
     * @throws NotFoundException
     * @throws UnexpectedValueException
     */
    protected function getCart(array $args = null): CartInterface
    {
        $cart = $this->isGuest($args)
            ? $this->getCartForGuest($args['guestCartId'])
            : $this->getCartForLoggedInUser();

        // We check cartId in case magento initializes new cart, if it is not found
        $cartId = $cart->getId();
        if ($cartId === null)
            throw new \UnexpectedValueException("Unable to retrieve cart, cart ID is null");

        return $cart;
    }

    /**
     * @param string|null $guestCartId
     * @return string
     * @throws NotFoundException
     * @throws UnexpectedValueException
     */
    protected function getCartId(array $args = null): string
    {
        return $this->isGuest($args)
            ? $args['guestCartId']
            : $this->getCartIdForLoggedInUser();
    }

    protected function CreatePaymentDetails(PaymentDetailsInterface $paymentInformation){
        $rawTotals = $paymentInformation->getTotals();

        return [
            'payment_methods' => array_map(
                function ($payment) {
                    /** @var PaymentMethodInterface $payment */
                    return [
                        'code' => $payment->getCode(),
                        'title' => $payment->getTitle(),
                    ];
                },
                $paymentInformation->getPaymentMethods()
            ),
            'totals' => array_merge(
                $rawTotals->getData(),
                [
                    'items_qty' => $rawTotals->getItemsQty(),
                    'items' => array_map(function ($item) {
                        /** @var TotalsItemInterface $item */
                        return [
                            TotalsItemInterface::KEY_ITEM_ID => $item->getItemId(),
                            TotalsItemInterface::KEY_PRICE => $item->getPrice(),
                            TotalsItemInterface::KEY_BASE_PRICE => $item->getBasePrice(),
                            TotalsItemInterface::KEY_QTY => $item->getQty(),
                            TotalsItemInterface::KEY_ROW_TOTAL => $item->getRowTotal(),
                            TotalsItemInterface::KEY_BASE_ROW_TOTAL => $item->getBaseRowTotal(),
                            TotalsItemInterface::KEY_ROW_TOTAL_WITH_DISCOUNT => $item->getRowTotalWithDiscount(),
                            TotalsItemInterface::KEY_TAX_AMOUNT => $item->getTaxAmount(),
                            TotalsItemInterface::KEY_BASE_TAX_AMOUNT => $item->getBaseTaxAmount(),
                            TotalsItemInterface::KEY_TAX_PERCENT => $item->getTaxPercent(),
                            TotalsItemInterface::KEY_DISCOUNT_AMOUNT => $item->getDiscountAmount(),
                            TotalsItemInterface::KEY_BASE_DISCOUNT_AMOUNT => $item->getBaseDiscountAmount(),
                            TotalsItemInterface::KEY_DISCOUNT_PERCENT => $item->getDiscountPercent(),
                            TotalsItemInterface::KEY_PRICE_INCL_TAX => $item->getPriceInclTax(),
                            TotalsItemInterface::KEY_BASE_PRICE_INCL_TAX => $item->getBasePriceInclTax(),
                            TotalsItemInterface::KEY_ROW_TOTAL_INCL_TAX => $item->getRowTotalInclTax(),
                            TotalsItemInterface::KEY_BASE_ROW_TOTAL_INCL_TAX => $item->getBaseRowTotalInclTax(),
                            TotalsItemInterface::KEY_OPTIONS => $item->getOptions(),
                            TotalsItemInterface::KEY_WEEE_TAX_APPLIED_AMOUNT => $item->getWeeeTaxAppliedAmount(),
                            TotalsItemInterface::KEY_WEEE_TAX_APPLIED => $item->getWeeeTaxApplied(),
                            TotalsItemInterface::KEY_NAME => $item->getName(),
                        ];
                    }, $rawTotals->getItems())]
            )
        ];
    }

    /**
     * @param array|null $args
     * @return bool
     */
    private function isGuest(array $args = null): bool
    {
        return array_key_exists('guestCartId', $args);
    }

    /**
     * @param string $guestCartId
     * @return CartInterface
     * @throws UnexpectedValueException
     */
    private function getCartForGuest(string $guestCartId)
    {
        try {
            return $this->guestCartRepository->get($guestCartId);
        } catch (NoSuchEntityException $e) {
            throw new \UnexpectedValueException("Unable to retrieve cart. guestCardId is invalid", 12, $e);
        }
    }

    /**
     * @return CartInterface
     * @throws UnexpectedValueException
     */
    private function getCartForLoggedInUser()
    {
        try {
            return $this->quoteManagement->getCartForCustomer($this->getCartIdForLoggedInUser());
        } catch (NoSuchEntityException $e) {
            throw new \UnexpectedValueException(__("Unable to retrieve cart. guestCartId is missing or you are not logged in"), 13, $e);
        }
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    private function getCartIdForLoggedInUser(): string
    {
        try {
            return $this->overriderCustomerId->getOverriddenValue();
            return $this->quoteManagement->getCartForCustomer($this->getCartIdForLoggedInUser());
        } catch (NoSuchEntityException $e) {
            throw new \UnexpectedValueException(__("Unable to retrieve cart. guestCartId is missing or you are not logged in"), 13, $e);
        }
    }
}
