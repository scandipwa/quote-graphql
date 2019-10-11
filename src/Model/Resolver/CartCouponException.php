<?php


namespace ScandiPWA\QuoteGraphQl\Model\Resolver;


use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class CartCouponException extends LocalizedException implements \GraphQL\Error\ClientAware
{
    const EXCEPTION_CATEGORY = 'cart-coupon';

    /**
     * Initialize object
     *
     * @param Phrase $phrase
     * @param \Exception $cause
     * @param int $code
     */
    public function __construct(Phrase $phrase, \Exception $cause = null, $code = 0)
    {
        parent::__construct($phrase, $cause, $code);
    }

    /**
     * @inheritdoc
     */
    public function isClientSafe() : bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getCategory() : string
    {
        return self::EXCEPTION_CATEGORY;
    }
}
