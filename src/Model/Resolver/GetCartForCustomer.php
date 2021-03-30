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

declare(strict_types=1);

namespace ScandiPWA\QuoteGraphQl\Model\Resolver;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\QuoteGraphQl\Model\CartItem\DataProvider\CustomizableOption;
use Magento\BundleGraphQl\Model\Cart\BundleOptionDataProvider;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use \Magento\Quote\Api\Data\AddressInterface;
use Magento\Tax\Model\Config;
use Magento\Downloadable\Model\LinkRepository;
use Magento\Downloadable\Model\Link;

class GetCartForCustomer extends CartResolver
{
    /** @var Configurable */
    protected $configurable;

    /** @var ProductFactory */
    protected $productFactory;

    /** @var DataPostProcessor */
    protected $productPostProcessor;

    /** @var array */
    protected $productsData;

    /** @var CustomizableOption */
    protected $customizableOption;

    /** @var BundleOptionDataProvider */
    protected $bundleOptions;

    /** @var Json */
    private $serializer;

    /** @var Config */
    private $config;

    /** @var LinkRepository */
    protected $linkRepository;

    /**
     * GetCartForCustomer constructor.
     * @param ParamOverriderCustomerId $overriderCustomerId
     * @param CartManagementInterface $quoteManagement
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param Configurable $configurable
     * @param ProductFactory $productFactory
     * @param DataPostProcessor $productPostProcessor
     * @param CustomizableOption $customizableOption
     * @param BundleOptionDataProvider $bundleOptions
     * @param Config $config
     * @param LinkRepository $linkRepository
     */
    public function __construct(
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GuestCartRepositoryInterface $guestCartRepository,
        Configurable $configurable,
        ProductFactory $productFactory,
        DataPostProcessor $productPostProcessor,
        CustomizableOption $customizableOption,
        BundleOptionDataProvider $bundleOptions,
        Json $serializer,
        Config $config,
        LinkRepository $linkRepository
    ) {
        parent::__construct(
            $guestCartRepository,
            $overriderCustomerId,
            $quoteManagement
        );

        $this->configurable = $configurable;
        $this->productFactory = $productFactory;
        $this->productPostProcessor = $productPostProcessor;
        $this->customizableOption = $customizableOption;
        $this->bundleOptions = $bundleOptions;
        $this->serializer = $serializer;
        $this->config = $config;
        $this->linkRepository = $linkRepository;
    }

    /**
     * @param QuoteItem $item
     * @param Product $product
     * @return array
     * @throws LocalizedException
     */
    protected function mergeQuoteItemData(
        QuoteItem $item,
        Product $product
    ) {
        return [
            'product' => $this->productsData[$product->getId()],
            'customizable_options' => $this->getCustomizableOptions($item),
            'bundle_options' => $this->bundleOptions->getData($item),
            'downloadable_links' => $this->getDownloadableLinks($item, $product)
        ] + $item->getData();
    }

    /**
     * @param $item
     * @param $product
     * @return array
     */
    private function getDownloadableLinks($item, $product): array
    {
        $quoteItemLinks= $item->getOptionByCode('downloadable_link_ids');

        if (null === $quoteItemLinks) {
            return [];
        }

        $downloadableLinks = [];
        $downloadableLinkIds = explode(',', $quoteItemLinks->getValue());
        $productLinks = $this->linkRepository->getLinksByProduct($product);

        /** @var Link $productLink  */
        foreach ($productLinks as $productLink) {
            if (in_array($productLink->getId(), $downloadableLinkIds)){
                $downloadableLinks[] = [
                    'label' => $productLink->getTitle(),
                    'id' => $productLink->getId()
                ];
            }
        }

        return $downloadableLinks;
    }


    /**
     * @param $item
     * @return array
     * @throws LocalizedException
     */
    private function getCustomizableOptions($item): array
    {
        $quoteItemOption = $item->getOptionByCode('option_ids');

        if (null === $quoteItemOption) {
            return [];
        }

        $customizableOptionsData = [];
        $customizableOptionIds = explode(',', $quoteItemOption->getValue());

        foreach ($customizableOptionIds as $customizableOptionId) {
            $customizableOption = $this->customizableOption->getData(
                $item,
                (int)$customizableOptionId
            );
            $customizableOptionsData[] = $customizableOption;
        }

        return $customizableOptionsData;
    }

    /**
     * @param AddressInterface $address
     * @return array
     */
    private function getAppliedTaxes(AddressInterface $address): array
    {
        $taxes = $address->getData('applied_taxes');

        if (is_string($taxes)) {
            $taxes = $this->serializer->unserialize($taxes);
        }

        return is_array($taxes) ? array_values($taxes) : [];
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return Value|CartInterface|mixed
     * @throws NotFoundException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $cart = $this->getCart($args);
        $items = $cart->getItems();
        $itemsData = [];

        if ($items) {
            // Prepare product data in advance
            $products = array_map(function ($item) {
                return $item->getProduct();
            }, $items);

            $adjustedInfo = $info->fieldNodes[0];
            $this->productsData = $this->productPostProcessor->process(
                $products,
                'items/product',
                $adjustedInfo
            );

            foreach ($items as $item) {
                /** @var QuoteItem $item */
                $product = $item->getProduct();
                $itemsData[] = $this->mergeQuoteItemData($item, $product);
            }
        }

        $cartData = $cart->getData();
        // In interface it is PHPDocumented that it returns bool,
        // while in implementation it returns int.
        $is_virtual = (bool)$cart->isVirtual();
        $address = $is_virtual ? $cart->getBillingAddress() : $cart->getShippingAddress();
        $tax_amount = $address->getTaxAmount();
        $discount_amount = $address->getDiscountAmount();
        $applied_taxes = $this->getAppliedTaxes($address);
        $grand_total = $address->getGrandTotal();
        $subtotal_incl_tax = $address->getSubtotalInclTax();
        $shipping_tax_amount = $address->getShippingTaxAmount();
        $shipping_amount = $address->getShippingAmount();
        $shipping_incl_tax = $address->getShippingInclTax();
        $shipping_method = $address->getShippingMethod();

        return array_merge(
            $cartData,
            [
                'items' => $itemsData,
                'tax_amount' => $tax_amount,
                'subtotal_incl_tax' => $subtotal_incl_tax,
                'discount_amount' => $discount_amount,
                'is_virtual' => $is_virtual,
                'applied_taxes' => $applied_taxes,
                'grand_total' => $grand_total,
                'shipping_tax_amount' => $shipping_tax_amount,
                'shipping_amount' => $shipping_amount,
                'shipping_incl_tax' => $shipping_incl_tax,
                'shipping_method' => $shipping_method
            ]
        );
    }
}
