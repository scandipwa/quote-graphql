<?php


namespace ScandiPWA\QuoteGraphQl\Model\Resolver;


use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class CartCouponException extends LocalizedException
{
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
}

