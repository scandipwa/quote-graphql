<?php


namespace ScandiPWA\QuoteGraphQl\Model\Resolver;


use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;

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
     * @throws NoSuchEntityException NotFoundException
     */
    protected function getCart(string $guestCartId = null): CartInterface
    {
        if ($guestCartId !== null) {
            // At this point we assume this is guest cart
            return $this->guestCartRepository->get($guestCartId);
        } else {
            try {
                // At this point we assume it is mine cart
                return $this->quoteManagement->getCartForCustomer(
                    $this->overriderCustomerId->getOverriddenValue()
                );
            } catch (NoSuchEntityException $e) {
                throw new NotFoundException("Unable to retrieve cart. guestCartId is missing or you are not logged in", 12, $e);
            }
        }
    }
}