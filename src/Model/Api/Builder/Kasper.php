<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/quote-graphql
 * @link    https://github.com/scandipwa/quote-graphql
 */

namespace ScandiPWA\QuoteGraphQl\Model\Api\Builder;

use Klarna\Core\Exception as KlarnaException;
use Klarna\Core\Helper\ConfigHelper;
use Klarna\Core\Helper\DataConverter;
use Klarna\Core\Helper\KlarnaConfig;
use Klarna\Core\Model\Api\Exception as KlarnaApiException;
use Klarna\Core\Model\Checkout\Orderline\Collector;
use Klarna\Core\Model\Fpt\Rate;
use Klarna\Kp\Api\Data\RequestInterface;
use Klarna\Kp\Model\Api\Request;
use Klarna\Kp\Model\Payment\Kp;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Url;
use Magento\Quote\Model\Quote\Address;

/**
 * Class Kasper
 *
 * @package ScandiPWA\QuoteGraphQl\Model\Api\Builder
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Kasper extends \Klarna\Core\Model\Api\Builder
{
    /**
     * @var Request\Builder
     */
    private $requestBuilder;
    /**
     * @var DataConverter
     */
    private $dataConverter;

    /** @var Rate $rate */
    private $rate;

    /**
     * Kasper constructor.
     *
     * @param EventManager                                $eventManager
     * @param Collector                                   $collector
     * @param Url                                         $url
     * @param ConfigHelper                                $configHelper
     * @param Rate                                        $rate
     * @param DirectoryHelper                             $directoryHelper
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $coreDate
     * @param DataObject\Copy                             $objCopyService
     * @param \Magento\Customer\Model\AddressRegistry     $addressRegistry
     * @param KlarnaConfig                                $klarnaConfig
     * @param DataConverter                               $dataConverter
     * @param Request\Builder                             $requestBuilder
     * @param DataObjectFactory                           $dataObjectFactory
     * @param array                                       $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        EventManager $eventManager,
        Collector $collector,
        Url $url,
        ConfigHelper $configHelper,
        Rate $rate,
        DirectoryHelper $directoryHelper,
        \Magento\Framework\Stdlib\DateTime\DateTime $coreDate,
        \Magento\Framework\DataObject\Copy $objCopyService,
        \Magento\Customer\Model\AddressRegistry $addressRegistry,
        KlarnaConfig $klarnaConfig,
        DataConverter $dataConverter,
        Request\Builder $requestBuilder,
        DataObjectFactory $dataObjectFactory,
        array $data = []
    ) {
        parent::__construct(
            $eventManager,
            $collector,
            $url,
            $configHelper,
            $directoryHelper,
            $coreDate,
            $objCopyService,
            $addressRegistry,
            $klarnaConfig,
            $dataObjectFactory,
            $data
        );
        $this->prefix = 'kp';
        $this->dataConverter = $dataConverter;
        $this->requestBuilder = $requestBuilder;
        $this->rate = $rate;
    }

    /**
     * Generate request
     *
     * @param string $type
     * @return $this
     * @throws \Klarna\Core\Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function generateRequest($type = self::GENERATE_TYPE_CREATE)
    {
        $this->resetOrderLines();
        parent::generateRequest($type);

        switch ($type) {
            case self::GENERATE_TYPE_CREATE:
            case self::GENERATE_TYPE_UPDATE:
                return $this->generateCreateUpdate();
            case self::GENERATE_TYPE_PLACE:
                return $this->generatePlace();
        }
        return $this;
    }

    /**
     * Generate Create/Update Request
     *
     * @return $this
     * @throws KlarnaApiException
     * @throws KlarnaException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateCreateUpdate()
    {
        $requiredAttributes = [
            'purchase_country',
            'purchase_currency',
            'locale',
            'order_amount',
            'orderlines',
        ];

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->getObject();
        $store = $quote->getStore();
        $options = array_map('trim', array_filter($this->configHelper->getCheckoutDesignConfig($store)));

        /**
         * Pre-fill customer details
         */
        $this->prefillAddresses($quote, $store);

        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

        $tax = $address->getBaseTaxAmount();
        if ($this->configHelper->isFptEnabled($store) && !$this->configHelper->getDisplayInSubtotalFpt($store)) {
            $fptResult = $this->rate->getFptTax($quote);
            $tax += $fptResult['tax'];
        }

        $this->requestBuilder->setPurchaseCountry($this->directoryHelper->getDefaultCountry($store))
            ->setPurchaseCurrency($quote->getBaseCurrencyCode())
            ->setLocale(str_replace('_', '-', $this->configHelper->getLocaleCode()))
            ->setOptions($options)
            ->setOrderAmount((int) $this->dataConverter->toApiFloat($address->getBaseGrandTotal()))
            ->addOrderlines($this->getOrderLines($quote->getStore()))
            ->setOrderTaxAmount((int) $this->dataConverter->toApiFloat($tax))
            ->setMerchantUrls($this->processMerchantUrls())
            ->validate($requiredAttributes, self::GENERATE_TYPE_CREATE);

        return $this;
    }

    /**
     * @param $quote
     * @param $store
     */
    public function prefillAddresses($quote, $store)
    {
        if (!$this->configHelper->isPaymentConfigFlag('data_sharing', $store, Kp::METHOD_CODE)) {
            return;
        }
        if ($this->configHelper->getApiConfig('api_version', $store) !== 'kp_na') {
            return;
        }
        $billingAddress = $this->getAddressData($quote, Address::TYPE_BILLING);
        if (!isset($billingAddress['country']) || $billingAddress['country'] !== 'US') {
            return;
        }
        $this->addBillingAddress($billingAddress);
        $this->addShippingAddress($this->getAddressData($quote, Address::TYPE_SHIPPING));
    }

    /**
     * @param array $create
     */
    private function addBillingAddress($address)
    {
        if ($this->validateAddress($address)) {
            $this->requestBuilder->setBillingAddress($address);
        }
    }

    /**
     * @param array $address
     * @return bool
     */
    private function validateAddress($address = null)
    {
        if ($address === null) {
            return false;
        }
        if (!is_array($address)) {
            return false;
        }
        if (!isset($address['email'])) {
            return false;
        }
        return true;
    }

    /**
     * @param array $create
     */
    private function addShippingAddress($address)
    {
        if ($this->validateAddress($address)) {
            $this->requestBuilder->setShippingAddress($address);
        }
    }

    /**
     * Pre-process Merchant URLs
     *
     * @param bool $nosid
     * @param bool $forced_secure
     * @return string[]
     */
    public function processMerchantUrls($nosid = true, $forced_secure = true)
    {
        /**
         * Urls
         */
        $urlParams = [
            '_nosid' => $nosid,
            '_forced_secure' => $forced_secure,
        ];

        $merchantUrls = $this->dataObjectFactory->create([
            'data' => [
                'confirmation' => $this->url->getDirectUrl('checkout/onepage/success', $urlParams),
                'notification' => $this->url->getDirectUrl('klarna/api/disabled', $urlParams),
            ],
        ]);

        $this->eventManager->dispatch(
            'klarna_prepare_merchant_urls',
            [
                'urls' => $merchantUrls,
                'url_params' => $this->dataObjectFactory->create(['data' => $urlParams]),
            ]
        );
        $data = $merchantUrls->toArray();

        $data['notification'] = preg_replace('/\/id\/{checkout\.order\.id}/', '', $data['notification']);
        unset($data['push']);

        return $data;
    }

    /**
     * Generate place order body
     *
     * @return Kasper
     * @throws \Klarna\Core\Exception
     * @throws \Klarna\Core\Model\Api\Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generatePlace()
    {
        $requiredAttributes = [
            'purchase_country',
            'purchase_currency',
            'locale',
            'order_amount',
            'orderlines',
            'merchant_urls'
        ];

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->getObject();
        $store = $quote->getStore();
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

        $tax = $address->getBaseTaxAmount();
        if ($this->configHelper->isFptEnabled($store) && !$this->configHelper->getDisplayInSubtotalFpt($store)) {
            $fptResult = $this->rate->getFptTax($quote);
            $tax += $fptResult['tax'];
        }

        $this->requestBuilder->setPurchaseCountry($this->directoryHelper->getDefaultCountry($store))
            ->setPurchaseCurrency($quote->getStore()->getBaseCurrencyCode())
            ->setLocale(str_replace('_', '-', $this->configHelper->getLocaleCode()))
            ->setOrderAmount((int) $this->dataConverter->toApiFloat($address->getBaseGrandTotal()))
            ->addOrderlines($this->getOrderLines($quote->getStore()))
            ->setOrderTaxAmount((int) $this->dataConverter->toApiFloat($tax))
            ->setMerchantUrls($this->processMerchantUrls())
            ->setMerchantReferences($this->getMerchantReferences($quote))
            ->validate($requiredAttributes, self::GENERATE_TYPE_PLACE);

        return $this;
    }

    /**
     * Get request
     *
     * @return RequestInterface
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getRequest()
    {
        return $this->requestBuilder->getRequest();
    }
}
