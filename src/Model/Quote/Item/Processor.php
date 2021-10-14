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

namespace ScandiPWA\QuoteGraphQl\Model\Quote\Item;

use Magento\Quote\Model\Quote\Item\Processor as SourceProcessor;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Catalog\Model\Product\Type as ProductType;

class Processor extends SourceProcessor
{
    /**
     * Set qty and custom price for quote item
     *
     * @param Item $item
     * @param DataObject $request
     * @param Product $candidate
     * @return void
     */
    public function prepare(Item $item, DataObject $request, Product $candidate): void
    {
        /**
         * We specify qty after we know about parent (for stock)
         */
        if ($request->getResetCount() && !$candidate->getStickWithinParent() && $item->getId() == $request->getId()) {
            $item->setData(CartItemInterface::KEY_QTY, 0);
        }

        $parent = $item->getParentItem();

        if ($candidate->getTypeId() !== ProductType::TYPE_BUNDLE
            && $parent
            && $parent->getProductType() === ProductType::TYPE_BUNDLE
        ) {
            $item->setQty($candidate->getCartQty());
        } else {
            $item->addQty($candidate->getCartQty());
        }


        if (!$item->getParentItem() || $item->getParentItem()->isChildrenCalculated()) {
            $item->setPrice($candidate->getFinalPrice());
        }

        $customPrice = $request->getCustomPrice();
        if (!empty($customPrice) && !$candidate->getParentProductId()) {
            $item->setCustomPrice($customPrice);
            $item->setOriginalCustomPrice($customPrice);
        }
    }
}
