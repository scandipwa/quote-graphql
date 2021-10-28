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

namespace ScandiPWA\QuoteGraphQl\Model\Cart\BuyRequest;

use Magento\Quote\Model\Cart\Data\CartItem;

class GroupedDataProvider
{
    private const OPTION_TYPE = 'grouped';

    /**
     * @inheritdoc
     *
     * @phpcs:disable Magento2.Functions.DiscouragedFunction
     */
    public function execute(CartItem $cartItem): array
    {
        $groupedData = [];

        foreach ($cartItem->getSelectedOptions() as $optionData) {
            $optionData = \explode('/', base64_decode($optionData->getId()));

            if ($this->isProviderApplicable($optionData) === false) {
                continue;
            }

            [, $simpleProductId, $quantity] = $optionData;

            $groupedData[$simpleProductId] = $quantity;
        }

        if (empty($groupedData)) {
            return $groupedData;
        }

        $result = ['super_group' => $groupedData];

        return $result;
    }

    /**
     * Checks whether this provider is applicable for the current option
     *
     * @param array $optionData
     *
     * @return bool
     */
    private function isProviderApplicable(array $optionData): bool
    {
        return $optionData[0] === self::OPTION_TYPE;
    }
}
