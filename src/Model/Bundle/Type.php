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

namespace ScandiPWA\QuoteGraphQl\Model\Bundle;

use Magento\Bundle\Model\Option;
use Magento\Bundle\Model\Product\Type as SourceType;
use Magento\Bundle\Model\ResourceModel\Option\Collection;
use Magento\Bundle\Model\ResourceModel\Selection\Collection\FilterApplier as SelectionCollectionFilterApplier;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\ArrayUtils;

class Type extends SourceType
{
    /**
     * @var ArrayUtils
     */
    protected $arrayUtility;

    public function __construct(
        \Magento\Catalog\Model\Product\Option $catalogProductOption,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Catalog\Model\Product\Type $catalogProductType,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\MediaStorage\Helper\File\Storage\Database $fileStorageDb,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Registry $coreRegistry,
        \Psr\Log\LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Helper\Product $catalogProduct,
        \Magento\Catalog\Helper\Data $catalogData,
        \Magento\Bundle\Model\SelectionFactory $bundleModelSelection,
        \Magento\Bundle\Model\ResourceModel\BundleFactory $bundleFactory,
        \Magento\Bundle\Model\ResourceModel\Selection\CollectionFactory $bundleCollection,
        \Magento\Catalog\Model\Config $config,
        \Magento\Bundle\Model\ResourceModel\Selection $bundleSelection,
        \Magento\Bundle\Model\OptionFactory $bundleOption,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\CatalogInventory\Api\StockStateInterface $stockState,
        ArrayUtils $arrayUtility,
        Json $serializer = null,
        MetadataPool $metadataPool = null,
        SelectionCollectionFilterApplier $selectionCollectionFilterApplier = null,
        UploaderFactory $uploaderFactory = null
    ) {
        $this->arrayUtility = $arrayUtility;
        parent::__construct($catalogProductOption, $eavConfig, $catalogProductType, $eventManager, $fileStorageDb, $filesystem, $coreRegistry, $logger, $productRepository, $catalogProduct, $catalogData, $bundleModelSelection, $bundleFactory, $bundleCollection, $config, $bundleSelection, $bundleOption, $storeManager, $priceCurrency, $stockRegistry, $stockState, $serializer, $metadataPool, $selectionCollectionFilterApplier, $arrayUtility, $uploaderFactory);
    }

    /**
     * Prepare product and its configuration to be added to some products list.
     *
     * Perform standard preparation process and then prepare of bundle selections options.
     *
     * @param \Magento\Framework\DataObject $buyRequest
     * @param \Magento\Catalog\Model\Product $product
     * @param string $processMode
     * @return \Magento\Framework\Phrase|array|string
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _prepareProduct(\Magento\Framework\DataObject $buyRequest, $product, $processMode)
    {
        $result = parent::_prepareProduct($buyRequest, $product, $processMode);

        try {
            if (is_string($result)) {
                throw new \Magento\Framework\Exception\LocalizedException(__($result));
            }

            $selections = [];
            $isStrictProcessMode = $this->_isStrictProcessMode($processMode);

            $skipSaleableCheck = $this->_catalogProduct->getSkipSaleableCheck();
            $_appendAllSelections = (bool)$product->getSkipCheckRequiredOption() || $skipSaleableCheck;

            $options = [];
            if ($buyRequest->getBundleOptionsData()) {
                $options = $this->getPreparedOptions($buyRequest->getBundleOptionsData());
            } else {
                $options = $buyRequest->getBundleOption();
            }

            if (is_array($options)) {
                $options = $this->recursiveIntval($options);
                $optionIds = array_keys($options);

                if (empty($optionIds) && $isStrictProcessMode) {
                    throw new \Magento\Framework\Exception\LocalizedException(__('Please specify product option(s).'));
                }

                $product->getTypeInstance()
                    ->setStoreFilter($product->getStoreId(), $product);
                $optionsCollection = $this->getOptionsCollection($product);
                $this->checkIsAllRequiredOptions(
                    $product,
                    $isStrictProcessMode,
                    $optionsCollection,
                    $options
                );

                $this->validateRadioAndSelectOptions(
                    $optionsCollection,
                    $options
                );

                $selectionIds = array_values($this->arrayUtility->flatten($options));
                // If product has not been configured yet then $selections array should be empty
                if (!empty($selectionIds)) {
                    $selections = $this->getSelectionsByIds($selectionIds, $product);

                    if (count($selections->getItems()) !== count($selectionIds)) {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('The options you selected are not available.')
                        );
                    }

                    // Check if added selections are still on sale
                    $this->checkSelectionsIsSale(
                        $selections,
                        $skipSaleableCheck,
                        $optionsCollection,
                        $options
                    );

                    $optionsCollection->appendSelections($selections, true, $_appendAllSelections);

                    $selections = $selections->getItems();
                } else {
                    $selections = [];
                }
            } else {
                $product->setOptionsValidationFail(true);
                $product->getTypeInstance()
                    ->setStoreFilter($product->getStoreId(), $product);

                $optionCollection = $product->getTypeInstance()
                    ->getOptionsCollection($product);
                $optionIds = $product->getTypeInstance()
                    ->getOptionsIds($product);
                $selectionCollection = $product->getTypeInstance()
                    ->getSelectionsCollection($optionIds, $product);
                $options = $optionCollection->appendSelections($selectionCollection, true, $_appendAllSelections);

                $selections = $this->mergeSelectionsWithOptions($options, $selections);
            }
            if ((is_array($selections) && count($selections) > 0) || !$isStrictProcessMode) {
                $uniqueKey = [$product->getId()];
                $selectionIds = [];
                if ($buyRequest->getBundleOptionsData()) {
                    $qtys = $buyRequest->getBundleOptionsData();
                } else {
                    $qtys = $buyRequest->getBundleOptionQty();
                }

                // Shuffle selection array by option position
                usort($selections, [$this, 'shakeSelections']);

                foreach ($selections as $selection) {
                    $selectionOptionId = $selection->getOptionId();
                    $qty = $this->getQty($selection, $qtys, $selectionOptionId);

                    $selectionId = $selection->getSelectionId();
                    $product->addCustomOption('selection_qty_' . $selectionId, $qty, $selection);
                    $selection->addCustomOption('selection_id', $selectionId);

                    $beforeQty = $this->getBeforeQty($product, $selection);
                    $product->addCustomOption('product_qty_' . $selection->getId(), $qty, $selection);

                    /*
                     * Create extra attributes that will be converted to product options in order item
                     * for selection (not for all bundle)
                     */
                    $price = $product->getPriceModel()
                        ->getSelectionFinalTotalPrice($product, $selection, 0, 1);
                    $attributes = [
                        'price' => $price,
                        'qty' => $qty,
                        'option_label' => $selection->getOption()
                            ->getTitle(),
                        'option_id' => $selection->getOption()
                            ->getId(),
                    ];

                    $_result = $selection->getTypeInstance()
                        ->prepareForCart($buyRequest, $selection);
                    $this->checkIsResult($_result);

                    $result[] = $_result[0]->setParentProductId($product->getId())
                        ->addCustomOption(
                            'bundle_option_ids',
                            $this->serializer->serialize(array_map('intval', $optionIds))
                        )
                        ->addCustomOption(
                            'bundle_selection_attributes',
                            $this->serializer->serialize($attributes)
                        );

                    if ($isStrictProcessMode) {
                        $_result[0]->setCartQty($qty);
                    }

                    $resultSelectionId = $_result[0]->getSelectionId();
                    $selectionIds[] = $resultSelectionId;
                    $uniqueKey[] = $resultSelectionId;
                    $uniqueKey[] = $qty;
                }

                // "unique" key for bundle selection and add it to selections and bundle for selections
                $uniqueKey = implode('_', $uniqueKey);
                foreach ($result as $item) {
                    $item->addCustomOption('bundle_identity', $uniqueKey);
                }
                $product->addCustomOption(
                    'bundle_option_ids',
                    $this->serializer->serialize(
                        array_map('intval', $optionIds)
                    )
                );
                $product->addCustomOption('bundle_selection_ids', $this->serializer->serialize($selectionIds));

                return $result;
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $e->getMessage();
        }

        return $this->getSpecifyOptionMessage();
    }

    /**
     * Cast array values to int
     *
     * @param array $array
     * @return int[]|int[][]
     */
    protected function recursiveIntval(array $array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->recursiveIntval($value);
            } elseif (is_numeric($value) && (int)$value != 0) {
                $array[$key] = (int)$value;
            } else {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Validate Options for Radio and Select input types
     *
     * @param Collection $optionsCollection
     * @param int[] $options
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function validateRadioAndSelectOptions($optionsCollection, $options): void
    {
        $errorTypes = [];

        if (is_array($optionsCollection->getItems())) {
            foreach ($optionsCollection->getItems() as $option) {
                if ($this->isSelectedOptionValid($option, $options)) {
                    $errorTypes[] = $option->getType();
                }
            }
        }

        if (!empty($errorTypes)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Option type (%types) should have only one element.',
                    ['types' => implode(", ", $errorTypes)]
                )
            );
        }
    }

    /**
     * Check if selected option is valid
     *
     * @param Option $option
     * @param array $options
     * @return bool
     */
    protected function isSelectedOptionValid($option, $options): bool
    {
        return (
            ($option->getType() == 'radio' || $option->getType() == 'select') &&
            isset($options[$option->getOptionId()]) &&
            is_array($options[$option->getOptionId()]) &&
            count($options[$option->getOptionId()]) > 1
        );
    }


    /**
     * Returns selection qty
     *
     * @param \Magento\Framework\DataObject $selection
     * @param int[] $qtys
     * @param int $selectionOptionId
     * @return float
     */
    protected function getQty($selection, $qtys, $selectionOptionId)
    {
        if ($selection->getSelectionCanChangeQty() && isset($qtys[$selectionOptionId])) {
            if (is_array($qtys[$selectionOptionId]) && isset($qtys[$selectionOptionId][$selection->getSelectionId()])) {
                $selectionQty = $qtys[$selectionOptionId][$selection->getSelectionId()];
                $qty = (float)$selectionQty > 0 ? $selectionQty : 1;
            } else {
                $qty = (float)$qtys[$selectionOptionId] > 0 ? $qtys[$selectionOptionId] : 1;
            }
        } else {
            $qty = (float)$selection->getSelectionQty() ? $selection->getSelectionQty() : 1;
        }

        $qty = (float)$qty;

        return $qty;
    }

    /**
     * Get prepared options with selection ids
     *
     * @param array $options
     * @return array
     */
    private function getPreparedOptions(array $options): array
    {
        foreach ($options as $optionId => $option) {
            foreach ($option as $selectionId => $optionQty) {
                $options[$optionId][$selectionId] = $selectionId;
            }
        }

        return $options;
    }
}
