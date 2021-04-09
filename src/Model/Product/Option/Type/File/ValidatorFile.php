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

namespace ScandiPWA\QuoteGraphQl\Model\Product\Option\Type\File;

use Magento\Catalog\Model\Product\Option\Type\File\ValidatorFile as OriginalValidatorFile;
use Magento\Framework\Math\Random;

/**
 * Validator class. Represents logic for validation file given from product option
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ValidatorFile extends OriginalValidatorFile
{
    /**
     * Constructor method
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\File\Size $fileSize
     * @param \Magento\Framework\HTTP\Adapter\FileTransferFactory $httpFactory
     * @param \Magento\Framework\Validator\File\IsImage $isImageValidator
     * @param Random|null $random
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\File\Size $fileSize,
        \Magento\Framework\HTTP\Adapter\FileTransferFactory $httpFactory,
        \Magento\Framework\Validator\File\IsImage $isImageValidator,
        Random $random = null
    ) {
        parent::__construct($scopeConfig, $filesystem, $fileSize, $httpFactory, $isImageValidator, $random);
    }

    /**
     * ScandiPWA uses completely different approach to upload files: we're sending base64-encoded file content in JSON
     * But standard FileValidator used by Magento expects file in the multipart/form-data request. That's why we're
     * getting rid of default validation logic.
     */
    public function validate($processingParams, $option)
    {
        return null;
    }
}
