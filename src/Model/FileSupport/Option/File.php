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

namespace ScandiPWA\QuoteGraphQl\Model\FileSupport\Option;

use Magento\Catalog\Model\Product\Exception as ProductException;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Model\Product\Option\Type\File as SourceFile;

class File extends SourceFile
{
    /**
     * @var Json
     */
    protected $serializer;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Item\OptionFactory $itemOptionFactory,
        \Magento\MediaStorage\Helper\File\Storage\Database $coreFileStorageDatabase,
        SourceFile\ValidatorInfo $validatorInfo,
        SourceFile\ValidatorFile $validatorFile,
        \Magento\Catalog\Model\Product\Option\UrlBuilder $urlBuilder,
        \Magento\Framework\Escaper $escaper,
        array $data = [],
        Filesystem $filesystem = null,
        Json $serializer = null,
        ProductHelper $productHelper = null
    ) {
        parent::__construct(
            $checkoutSession,
            $scopeConfig,
            $itemOptionFactory,
            $coreFileStorageDatabase,
            $validatorInfo,
            $validatorFile,
            $urlBuilder,
            $escaper,
            $data,
            $filesystem,
            $serializer,
            $productHelper
        );

        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
    }

    /**
     * Prepare option value for cart
     *
     * @return string|null Prepared option value
     */
    public function prepareForCart()
    {
        $option = $this->getOption();
        $optionId = $option->getId();
        $buyRequest = $this->getRequest();

        // Prepare value and fill buyRequest with option
        $requestOptions = $buyRequest->getOptions();
        if ($this->getIsValid() && $this->getUserValue() !== null) {
            $value = $this->getUserValue();

            // Save option in request, because we have no $_FILES['options']
            $requestOptions[$this->getOption()->getId()] = $value;
            $result = $this->serializer->serialize($value);
        } else if ($this->getIsValid() && isset($requestOptions[$this->getOption()->getId()])) {
            $result = $this->serializer->serialize($requestOptions[$this->getOption()->getId()]);
        } else {
            /*
             * Clear option info from request, so it won't be stored in our db upon
             * unsuccessful validation. Otherwise some bad file data can happen in buyRequest
             * and be used later in reorders and reconfigurations.
             */
            if (is_array($requestOptions)) {
                unset($requestOptions[$this->getOption()->getId()]);
            }
            $result = null;
        }
        $buyRequest->setOptions($requestOptions);

        // Clear action key from buy request - we won't need it anymore
        $optionActionKey = 'options_' . $optionId . '_file_action';
        $buyRequest->unsetData($optionActionKey);

        return $result;
    }
}
