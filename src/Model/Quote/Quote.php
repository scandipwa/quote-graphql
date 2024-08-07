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
namespace ScandiPWA\QuoteGraphQl\Model\Quote;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote as SourceQuote;

/**
 * Class Quote
 * @package ScandiPWA\QuoteGraphQl\Model\Quote
 */
class Quote extends SourceQuote
{
    /**
     * @return $this|Quote
     */
    public function beforeSave()
    {
        parent::beforeSave();
        $customerIsGuest = !$this->_customer || $this->getCustomerId() == null;
        $this->setCustomerIsGuest($customerIsGuest);
        return $this;
    }

    public function addProduct(
        Product $product,
                $request = null,
                $processMode = AbstractType::PROCESS_MODE_FULL
    )
    {
        if ($request === null) {
            $request = 1;
        }

        if (is_numeric($request)) {
            $request = $this->objectFactory->create(['qty' => $request]);
        }

        if (!$request instanceof DataObject) {
            throw new LocalizedException(
                __('We found an invalid request for adding product to quote.')
            );
        }

        if (!$product->isSalable()) {
            throw new LocalizedException(
                __('Product that you are trying to add is not available.')
            );
        }

        $productId = $product->getId();
        $product = clone $this->productRepository->getById($productId, false, $this->getStore()->getId());

        return parent::addProduct($product, $request, $processMode);
    }
}
