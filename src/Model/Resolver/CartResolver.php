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
     * @param array|null $args
     * @return bool
     */
    protected function isGuest(array $args = null): bool
    {
        return array_key_exists('guestCartId', $args);
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

    /**
     * @return string
     * @throws UnexpectedValueException
     */
    private function getCartIdForLoggedInUser(): string
    {
        try {
            return $this->overriderCustomerId->getOverriddenValue();
        } catch (NoSuchEntityException $e) {
            throw new \UnexpectedValueException(__("Unable to retrieve cart id. guestCartId is missing or you are not logged in"), 13, $e);
        }
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
}
