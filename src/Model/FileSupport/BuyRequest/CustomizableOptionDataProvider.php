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

namespace ScandiPWA\QuoteGraphQl\Model\FileSupport\BuyRequest;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Cart\Data\CartItem;
use Magento\Framework\Filesystem;
use Magento\Quote\Model\Cart\BuyRequest\BuyRequestDataProviderInterface;

/**
 * Extract buy request elements require for custom options
 */
class CustomizableOptionDataProvider implements BuyRequestDataProviderInterface
{
    const OPTION_TYPE = 'custom-option';
    const QUOTE_MEDIA_PATH = 'custom_options/quote/';
    const ORDER_MEDIA_PATH = 'custom_options/order/';

    /**
     * @var string
     */
    protected $mediaPath;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(
        Filesystem $filesystem
    ) {
        $this->mediaPath = $filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function execute(CartItem $cartItem): array
    {
        $customizableOptionsData = [];

        foreach ($cartItem->getSelectedOptions() as $optionData) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $optionData = \explode('/', base64_decode($optionData->getId()));

            if ($this->isProviderApplicable($optionData) === false) {
                continue;
            }

            $this->validateInput($optionData);

            [$optionType, $optionId, $optionValue] = $optionData;

            // Handle previously uploaded file when add product with 'File' customizable option to wishlist
            if(strpos($optionValue, "file-") === 0){
                [$filePrefix, $encodedFileInfo] = \explode('-', $optionValue);
                $optionValue = json_decode(base64_decode($encodedFileInfo));
            }

            if ($optionType == self::OPTION_TYPE) {
                $customizableOptionsData[$optionId][] = $optionValue;
            }
        }

        foreach ($cartItem->getEnteredOptions() as $option) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $optionData = \explode('/', base64_decode($option->getUid()));

            if ($this->isProviderApplicable($optionData) === false) {
                continue;
            }

            [$optionType, $optionId] = $optionData;

            // -- File Upload Suppor --
            $fileData = $this->getFileData($option->getUid(), $option->getValue());
            if ($optionType == self::OPTION_TYPE) {
                if ($fileData !== false) {
                    $this->createFileAndFolder($option->getUid(), $fileData['raw'], $fileData['title']);
                    unset($fileData['raw']);
                    $customizableOptionsData[$optionId][] = $fileData;
            // -- End of Support --
                } else {
                    $customizableOptionsData[$optionId][] = $option->getValue();
                }
            }
        }

        $output = ['options' => $this->flattenOptionValues($customizableOptionsData)];

        return $output;
    }

    private function getFileData($uid, $optionData) {
        try {
            $data = json_decode($optionData, true);

            if (!is_array($data)) {
                return false;
            }

            $filename = $data['file_name'];
            $filedata = $data['file_data'];

            $insidePath = $uid . '/_/' . $filename;

            if (!$filename || !$filedata) {
                return false;
            }

            return [
                'type' => 'application/octet-stream',
                'title' => $filename,
                'quote_path' => self::QUOTE_MEDIA_PATH . $insidePath,
                'order_path' => self::ORDER_MEDIA_PATH . $insidePath,
                'fullpath' => $this->mediaPath . self::QUOTE_MEDIA_PATH . $insidePath,
                'secret_key' => $filename,
                'raw' => $filedata
            ];
        } catch(\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * @param $quoteId
     * @param $value
     * @param $filename
     */
    public function createFileAndFolder($uid, $value, $filename)
    {
        $directory = sprintf(
            '%s%s%s/_',
            $this->mediaPath,
            self::QUOTE_MEDIA_PATH,
            $uid
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
     * Flatten option values for non-multiselect customizable options
     *
     * @param array $customizableOptionsData
     * @return array
     */
    protected function flattenOptionValues(array $customizableOptionsData): array
    {
        foreach ($customizableOptionsData as $optionId => $optionValue) {
            if (count($optionValue) === 1) {
                $customizableOptionsData[$optionId] = $optionValue[0];
            }
        }

        return $customizableOptionsData;
    }

    /**
     * Checks whether this provider is applicable for the current option
     *
     * @param array $optionData
     * @return bool
     */
    protected function isProviderApplicable(array $optionData): bool
    {
        if ($optionData[0] !== self::OPTION_TYPE) {
            return false;
        }

        return true;
    }

    /**
     * Validates the provided options structure
     *
     * @param array $optionData
     * @throws LocalizedException
     */
    protected function validateInput(array $optionData): void
    {
        if (count($optionData) !== 3) {
            throw new LocalizedException(
                __('Wrong format of the entered option data')
            );
        }
    }
}
