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

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Quote\Model\Quote\Validator\MinimumOrderAmount\ValidationMessage;

/**
 * Class CartMinimumOrderAmount
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class CartMinimumOrderAmount implements ResolverInterface
{
    /**
     * @var ValidationMessage
     */
    protected ValidationMessage $amountValidationMessage;

    /**
     * CartMinimumOrderAmount constructor.
     * @param ValidationResultFactory $validationResultFactory
     */
    public function __construct(
        ValidationMessage $amountValidationMessage
    ) {
        $this->amountValidationMessage = $amountValidationMessage;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws NotFoundException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $quote = $value['model'];

        $minimumOrderAmountReached = $quote->validateMinimumAmount();
        $minimumOrderDescription = $this->amountValidationMessage->getMessage();

        return [
            'minimum_order_amount_reached' => $minimumOrderAmountReached,
            'minimum_order_description' => $minimumOrderDescription
        ];
    }
}
