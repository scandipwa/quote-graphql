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

namespace ScandiPWA\QuoteGraphQl\Helper;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Item\Option;
use Magento\Quote\Model\Quote\Item\OptionFactory;

class ImageUpload {
    const QUOTE_MEDIA_PATH = 'custom_options/quote/';
    const ORDER_MEDIA_PATH = 'custom_options/order/';

    /**
     * @var OptionFactory
     */
    protected $optionFactory;

    /**
     * @var string
     */
    protected $mediaPath;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * ImageUpload constructor.
     * @param OptionFactory $optionFactory
     * @param Filesystem $filesystem
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        OptionFactory $optionFactory,
        Filesystem $filesystem,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->optionFactory = $optionFactory;
        $this->mediaPath = $filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param $quote
     * @param $requestCartItem
     * @throws Exception
     */
    public function processFileUpload($quote, $requestCartItem)
    {
        $options = $requestCartItem['product_option']['extension_attributes']['customizable_options'] ?? false;

        if ($options) {
            $quoteItem = array_slice($quote->getItems(), -1)[0];
            $existingOptionsIds = $quoteItem->getOptionByCode('option_ids') ?? $this->createOptionIds($quoteItem);
            $existingOptionsIdsValue = $existingOptionsIds->getValue();
            $isNecessaryToUpdateQuote = false;

            foreach ($options as $option) {
                $optionId = $option['option_id'];
                $optionValue = $option['option_value'];
                $quoteId = $quote->getId();
                $productId = $quoteItem->getProductId();

                if (
                    !in_array($optionId, explode(',', $existingOptionsIdsValue))
                    && strpos($optionValue, 'base64')
                ) {
                    $filename = $option['option_filename'] ?? base64_encode(sprintf(
                        '%s-%s-%s',
                        $quoteId,
                        $productId,
                        time()
                    ));
                    $data = $this->getOptionData($quoteId, $filename);

                    $existingOptionsIds->setValue(sprintf(
                        '%s%s',
                        $existingOptionsIdsValue ? $existingOptionsIdsValue . ',' : '',
                        $optionId
                    ))->save();

                    $buyRequest = $quoteItem->getOptionByCode('info_buyRequest');
                    $buyRequestValue = (array) json_decode($buyRequest->getValue());
                    $buyRequestOptions = (array) $buyRequestValue['options'];
                    $buyRequestOptions[$optionId] = $data;
                    $buyRequestValue['options'] = $buyRequestOptions;
                    $buyRequest->setValue(json_encode($buyRequestValue))->save();

                    $this->optionFactory->create()->setData([
                        'item_id' => $quoteItem->getId(),
                        'product_id' => $productId,
                        'code' => 'option_' . $optionId,
                        'value' => json_encode($data)
                    ])->save();

                    $this->createFileAndFolder($quoteId, $optionValue , $filename);
                    $isNecessaryToUpdateQuote = true;
                }
            }

            // We need to get quote with updated options and re-save it to update price for quote item
            // Otherwise quote will not have correct price until it will be updated
            if ($isNecessaryToUpdateQuote) {
                $quote = $this->quoteRepository->getActive($quoteId);
                $quote->setTotalsCollectedFlag(false)->collectTotals();
                $this->quoteRepository->save($quote);
            }
        }
    }

    /**
     * @param $quoteId
     * @param $value
     * @param $filename
     */
    public function createFileAndFolder($quoteId, $value, $filename)
    {
        $directory = sprintf(
            '%s%s%s/_',
            $this->mediaPath,
            self::QUOTE_MEDIA_PATH,
            $quoteId
        );

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            sprintf('%s/%s', $directory, $filename),
            base64_decode(substr($value, strpos($value, ',') + 1 ))
        );
    }

    /**
     * Get data for quote_item_options table
     *
     * @param $quoteId
     * @param $filename
     * @return array
     */
    public function getOptionData($quoteId, $filename)
    {
        $insidePath = $quoteId . '/_/' . $filename;

        return [
            'type' => 'application/octet-stream',
            'title' => $filename,
            'quote_path' => self::QUOTE_MEDIA_PATH . $insidePath,
            'order_path' => self::ORDER_MEDIA_PATH . $insidePath,
            'fullpath' => $this->mediaPath . self::QUOTE_MEDIA_PATH . $insidePath,
            'secret_key' => $filename
        ];
    }

    /**
     * @param $quoteItem
     * @return Option
     * @throws Exception
     */
    public function createOptionIds($quoteItem)
    {
        return $this->optionFactory->create()->setData([
            'item_id' => $quoteItem->getId(),
            'product_id' => $quoteItem->getProductId(),
            'code' => 'option_ids',
            'value' => ''
        ])->save();
    }
}
