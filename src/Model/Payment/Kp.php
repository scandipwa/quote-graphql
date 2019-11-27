<?php
/**
 * ScandiPWA_QuoteGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @author      Artjoms Travkovs <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\QuoteGraphQl\Model\Payment;

use Klarna\Kp\Model\Payment\Kp as KpSource;
use Magento\Payment\Model\Method\Adapter;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Kp extends KpSource
{
    /**
     * @var Adapter
     */
    private $adapter;

    /**
     * @param Adapter $adapter
     * @param Resolver $resolver
     * @param ScopeConfigInterface $config
     * @param \Klarna\Kp\Model\SessionInitiatorFactory $sessionInitiatorFactory
     */
    public function __construct(
        Adapter $adapter,
        Resolver $resolver,
        ScopeConfigInterface $config,
        \Klarna\Kp\Model\SessionInitiatorFactory $sessionInitiatorFactory
    ) {
        parent::__construct($adapter, $resolver, $config, $sessionInitiatorFactory);
        $this->adapter = $adapter;
    }

    /**
     * {@inheritDoc}
     */
    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return !!$this->adapter->isAvailable($quote);
    }
}
