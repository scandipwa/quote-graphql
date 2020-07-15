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

use Exception;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Phrase;
use Magento\InventoryConfigurationApi\Exception\SkuIsNotAssignedToStockException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Bundle\Model\Product\Type;
use Magento\Framework\DataObject;
use Magento\Catalog\Model\Product\Attribute\Repository;
use Magento\CatalogInventory\Api\StockStatusRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;

/**
 * Class SaveCartItem
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class SaveCartItem implements ResolverInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ParamOverriderCartId
     */
    protected $overriderCartId;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var Repository
     */
    protected $attributeRepository;

    /**
     * @var QuoteIdMask
     */
    protected $quoteIdMaskResource;

    /**
     * @var Configurable
     */
    protected $configurableType;

    /**
     * @var StockStatusRepositoryInterface
     */
    protected $stockStatusRepository;

    /**
     * @var GetStockItemDataInterface
     */
    private $getStockItemData;

    /**
     * @var GetReservationsQuantityInterface
     */
    private $getReservationsQuantity;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * SaveCartItem constructor.
     *
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param ParamOverriderCartId $overriderCartId
     * @param ProductRepository $productRepository
     * @param Repository $attributeRepository
     * @param QuoteIdMask $quoteIdMaskResource
     * @param Configurable $configurableType
     * @param StockStatusRepositoryInterface $stockStatusRepository
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $quoteRepository,
        ParamOverriderCartId $overriderCartId,
        ProductRepository $productRepository,
        Repository $attributeRepository,
        QuoteIdMask $quoteIdMaskResource,
        Configurable $configurableType,
        StockStatusRepositoryInterface $stockStatusRepository,
        GetStockItemDataInterface $getStockItemData,
        GetReservationsQuantityInterface $getReservationsQuantity,
        GetStockItemConfigurationInterface $getStockItemConfiguration
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteRepository = $quoteRepository;
        $this->overriderCartId = $overriderCartId;
        $this->productRepository = $productRepository;
        $this->attributeRepository = $attributeRepository;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
        $this->configurableType = $configurableType;
        $this->stockStatusRepository = $stockStatusRepository;
        $this->getStockItemData = $getStockItemData;
        $this->getReservationsQuantity = $getReservationsQuantity;
        $this->getStockItemConfiguration = $getStockItemConfiguration;
    }

    /**
     * @param array $options
     * @return array
     */
    private function prepareOptions(array $options): array
    {
        if (isset ($options['product_option']['extension_attributes']['configurable_item_options'])) {
            $configurableOptions = &$options['product_option']['extension_attributes']['configurable_item_options'];
            $stringifiedOptionValues = array_map(function ($item) {
                $item['option_value'] = (string)$item['option_value'];
                return $item;
            }, $configurableOptions);
            $configurableOptions = $stringifiedOptionValues;
        }

        return $options;
    }

    /**
     * @param Product $product
     * @param array   $options
     * @return DataObject
     */
    private function prepareAddItem(Product $product, array $options): DataObject
    {
        $options = $this->prepareOptions($options);
        $data = [
            'product' => $product->getEntityId(),
            'qty' => $options['quantity']
        ];

        switch ($product->getTypeId()) {
            case Configurable::TYPE_CODE:
                $data = $this->setConfigurableRequestOptions($options, $data);
                break;
            case Type::TYPE_CODE:
                $data = $this->setBundleRequestOptions($options, $data);
                break;
            case ProductType::TYPE_SIMPLE:
            case ProductType::TYPE_VIRTUAL:
                $this->setCustomizableOptions($options, $data);
                break;
        }

        $request = new DataObject();
        $request->setData($data);

        return $request;
    }

    /**
     * @param array $options
     * @param array $data
     * @return array
     */
    private function setConfigurableRequestOptions(array $options, array $data): array
    {
        $configurableOptions = $options['product_option']['extension_attributes']['configurable_item_options'] ?? [];
        $superAttributes = [];

        foreach ($configurableOptions as $option) {
            $superAttributes[$option['option_id']] = $option['option_value'];
        }

        $data['super_attribute'] = $superAttributes;
        return $data;
    }

    /**
     * @param array $options
     * @param array $data
     */
    private function setCustomizableOptions(array $options, array &$data): void
    {
        $customizableOptionsData = $options['product_option']['extension_attributes']['customizable_options'] ?? [];
        $customizableOptions = $this->getCustomizableOptions($customizableOptionsData);
        // Necessary for multi selections, i.e., checkboxes which have same parent option_id
        $customizableOptionsArrayData = $options['product_option']['extension_attributes']['customizable_options_multi'] ?? [];
        $customizableOptionsMulti = $this->getCustomizableOptions($customizableOptionsArrayData, true);

        if (count($customizableOptions)) {
            foreach ($customizableOptions as $key => $value) {
                $data['options'][$key] = $value;
            }
        }

        if (count($customizableOptionsMulti)) {
            foreach ($customizableOptionsMulti as $key => $value) {
                $data['options'][$key] = $value;
            }
        }
    }

    /**
     * @param $customizableOptions
     * @param bool $isMulti
     * @return array
     */
    private function getCustomizableOptions($customizableOptions, $isMulti = false): array
    {
        $data = [];

        if (count($customizableOptions)) {
            foreach ($customizableOptions as $customizableOption) {
                if ($isMulti) {
                    $data[$customizableOption['option_id']][] = $customizableOption['option_value'];
                } else {
                    $data[$customizableOption['option_id']] = $customizableOption['option_value'];
                }
            }
        }

        return $data;
    }

    /**
     * @param array $options
     * @param array $data
     * @return array
     */
    private function setBundleRequestOptions(array $options, array $data): array
    {
        $data['bundle_option'] = [];
        $data['bundle_option_qty'] = [];
        $bundleOptions = $options['product_option']['extension_attributes']['bundle_options'] ?? [];

        foreach ($bundleOptions as $bundleOption) {
            $optionId = $bundleOption['id'];
            $data['bundle_option'][$optionId][] = $bundleOption['value'];
            $data['bundle_option_qty'][$optionId] = $bundleOption['quantity'];
        }

        return $data;
    }

    /**
     * @param string $guestCardId
     * @return string
     */
    protected function getGuestQuoteId(string $guestCardId): string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResource->load($quoteIdMask, $guestCardId, 'masked_id');

        return $quoteIdMask->getQuoteId();
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field            $field
     * @param ContextInterface $context
     * @param ResolveInfo      $info
     * @param array|null       $value
     * @param array|null       $args
     * @return mixed|Value
     * @throws Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        $requestCartItem = $args['cartItem'];
        if (!$this->validateCartItem($requestCartItem)) {
            throw new GraphQlInputException(new Phrase('Cart item ID or product SKU must be passed'));
        }
        $quoteId = isset($args['guestCartId'])
            ? $this->getGuestQuoteId($args['guestCartId'])
            : $this->overriderCartId->getOverriddenValue();
        $quote = $this->quoteRepository->getActive($quoteId);
        ['quantity' => $qty] = $requestCartItem;

        $itemId = $this->getItemId($requestCartItem);

        if ($itemId) {
            $cartItem = $quote->getItemById($itemId);
            $this->checkItemQty($cartItem, $qty);

            $cartItem->setQty($qty);
            $this->quoteRepository->save($quote);
        } else {
            $sku = $this->getSku($requestCartItem);
            $product = $this->productRepository->get($sku);

            if (!$product) {
                throw new GraphQlNoSuchEntityException(new Phrase('Product could not be loaded'));
            }

            $newQuoteItem = $this->buildQuoteItem(
                $sku,
                $qty,
                (int) $quoteId,
                $requestCartItem['product_option'] ?? []
            );

            try {
                $result = $quote->addProduct($product, $this->prepareAddItem(
                    $product,
                    $newQuoteItem
                ));
                if (is_string($result)){
                    throw new GraphQlInputException(new Phrase($result));
                }

                $this->quoteRepository->save($quote);
            } catch (\Exception $e) {
                throw new GraphQlInputException(new Phrase($e->getMessage()));
            }

            // Related to bug: https://github.com/magento/magento2/issues/2991
            $quote = $this->quoteRepository->getActive($quoteId);
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->quoteRepository->save($quote);
        }

        return [];
    }

    /**
     * @param CartItemInterface $cartItem
     * @param $qty
     * @throws GraphQlInputException
     * @throws LocalizedException
     * @throws SkuIsNotAssignedToStockException
     */
    protected function checkItemQty(CartItemInterface $cartItem, $qty): void
    {
        $product = $cartItem->getProduct();

        if ($cartItem->getProductType() === Configurable::TYPE_CODE) {
            $attributesInfo = $cartItem->getBuyRequest()->getDataByKey('super_attribute');
            $product = $this->configurableType->getProductByAttributes($attributesInfo, $product);
        }

        $stockStatus = $this->stockStatusRepository->get($product->getId());
        $stockItem = $stockStatus->getStockItem();

        if (!$stockItem->getManageStock()) { // just skip all checks, if stock is not managed
            return;
        }

        $fitsInStock = $qty <= $stockItem->getQty();
        $isInMinMaxSaleRange = $qty >= $stockItem->getMinSaleQty() || $qty <= $stockItem->getMaxSaleQty();

        if (!($fitsInStock && $isInMinMaxSaleRange)) {
            throw new GraphQlInputException(new Phrase('Provided quantity exceeds stock limits'));
        }

        $stockId = $stockItem->getStockId();
        $sku = $product->getSku();

        $stockItemData = $this->getStockItemData->execute($sku, $stockId);

        /** @var StockItemConfigurationInterface $stockItemConfiguration */
        $stockItemConfiguration = $this->getStockItemConfiguration->execute($sku, $stockId);

        $qtyWithReservation = $stockItemData[GetStockItemDataInterface::QUANTITY] +
            $this->getReservationsQuantity->execute($sku, $stockId);

        $qtyLeftInStock = $qtyWithReservation - $stockItemConfiguration->getMinQty();

        $isInStock = bccomp((string) $qtyLeftInStock, (string) $qty, 4) >= 0;
        $isEnoughQty = (bool)$stockItemData[GetStockItemDataInterface::IS_SALABLE] && $isInStock;

        if (!$isEnoughQty) {
            throw new GraphQlInputException(new Phrase('The requested quantity is not available'));
        }
    }

    /**
     * @param string $sku
     * @param float    $qty
     * @param int    $quoteId
     * @param array  $options
     * @return array
     */
    protected function buildQuoteItem(string $sku, float $qty, int $quoteId, array $options = []): array
    {
        return [
            'quantity' => $qty,
            'sku' => $sku,
            'quote_id' => $quoteId,
            'product_option' => $options
        ];
    }

    /**
     * @param array $cartItem
     * @return bool
     */
    private function isIdStructUsed(array $cartItem): bool
    {
        return array_key_exists('id', $cartItem) && is_array($cartItem['id']);
    }

    /**
     * @param array $cartItem
     * @return int|null
     */
    protected function getItemId(array $cartItem): ?int
    {
        if (isset($cartItem['item_id'])) {
            return $cartItem['item_id'];
        }

        if ($this->isIdStructUsed($cartItem)) {
            return $this->getItemId($cartItem['id']);
        }

        return null;
    }

    /**
     * @param array $cartItem
     * @return string|null
     */
    protected function getSku(array $cartItem): ?string
    {
        if (isset($cartItem['sku'])) {
            return $cartItem['sku'];
        }

        if ($this->isIdStructUsed($cartItem)) {
            return $this->getSku($cartItem['id']);
        }

        return null;
    }

    /**
     * @param array $cartItem
     * @return bool
     */
    protected function validateCartItem(array $cartItem): bool
    {
        return isset($cartItem['item_id']) || isset($cartItem['sku']) || isset($cartItem['id']);
    }
}
