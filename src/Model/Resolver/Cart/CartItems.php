<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/quote-graphql
 * @link https://github.com/scandipwa/quote-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\QuoteGraphQl\Model\Resolver\Cart;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\QuoteGraphQl\Model\Cart\GetCartProducts;
use Magento\QuoteGraphQl\Model\Resolver\CartItems as SourceCartItems;
use Magento\Catalog\Helper\Image;

class CartItems extends SourceCartItems
{
    public const THUMBNAIL = 'thumbnail';

    /**
     * @var Images
     */
    protected Images $imageHelper;

    /**
     * @param GetCartProducts $getCartProducts
     * @param Uid $uidEncoder
     * @param Image $imageHelper
     */
    public function __construct(
        GetCartProducts $getCartProducts,
        Uid $uidEncoder,
        Image $imageHelper
    ) {
        $this->imageHelper = $imageHelper;
        parent::__construct($getCartProducts, $uidEncoder);
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $itemsData = parent::resolve($field, $context, $info, $value, $args);

        if (!empty($itemsData)) {
            foreach ($itemsData as &$item) {
                $product = $item['model']->getProduct();
                $path = $product->getData(self::THUMBNAIL);
                $item['product'][self::THUMBNAIL] = [
                    'url' => $this->getImageUrl(
                        self::THUMBNAIL,
                        $path,
                        $product
                    )
                ];
            }
        }

        return $itemsData;
    }

    /**
     * @param string $imageType
     * @param string|null $imagePath
     * @param $product
     * @return string
     */
    protected function getImageUrl(
        string $imageType,
        ?string $imagePath,
        $product
    ): string {
        if (!isset($imagePath)) {
            return $this->imageHelper->getDefaultPlaceholderUrl($imageType);
        }

        $imageId = sprintf('scandipwa_%s', $imageType);

        $imageUrl = $this->imageHelper
            ->init(
                $product,
                $imageId,
                ['type' => $imageType]
            )
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepTransparency(true)
            ->keepFrame(false)
            ->getUrl();

        return $imageUrl;
    }
}
