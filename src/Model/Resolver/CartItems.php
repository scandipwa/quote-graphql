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

use Magento\Catalog\Helper\Image as HelperImage;
use Magento\Framework\App\Area;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\QuoteGraphQl\Model\Cart\GetCartProducts;
use Magento\QuoteGraphQl\Model\Resolver\CartItems as SourceCartItems;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class CartItems
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class CartItems extends SourceCartItems
{
    /**
     * @var GetCartProducts
     */
    protected GetCartProducts $getCartProducts;

    /**
     * @var Uid
     */
    protected Uid $uidEncoder;

    /**
     * @var Emulation
     */
    protected Emulation $emulation;

    /**
     * @var HelperImage
     */
    protected HelperImage $helperImage;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @param GetCartProducts $getCartProducts
     * @param Uid $uidEncoder
     * @param Emulation $emulation
     * @param HelperImage $helperImage
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        GetCartProducts $getCartProducts,
        Uid $uidEncoder,
        Emulation $emulation,
        HelperImage $helperImage,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct(
            $getCartProducts,
            $uidEncoder
        );

        $this->emulation = $emulation;
        $this->helperImage = $helperImage;
        $this->storeManager = $storeManager;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $result = parent::resolve($field, $context, $info, $value, $args);

        $cartItems = [];

        $storeId = $this->storeManager->getStore()->getId();
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        foreach ($result as $itemData) {
            $cartItem = $itemData['model'];
            $product = $cartItem->getProduct();

            $cartItems[] = array_merge($itemData, [
                'sku' => $cartItem->getSku(),
                'product' => array_merge($itemData['product'],
                [
                    'thumbnail' =>
                        [
                            'path' => $product->getThumbnail(),
                            'url' => $this->getImageUrl('thumbnail', $product->getThumbnail(), $product)
                        ]
                ])
            ]);
        }

        $this->emulation->stopEnvironmentEmulation();

        return $cartItems;
    }

    /**
     * @param string $imageType
     * @param string|null $imagePath
     * @param Product $product
     * @return string
     */
    protected function getImageUrl(
        string $imageType,
        ?string $imagePath,
        $product
    ): string {
        if (!isset($imagePath)) {
            return $this->helperImage->getDefaultPlaceholderUrl($imageType);
        }

        $imageId = sprintf('scandipwa_%s', $imageType);

        $image = $this->helperImage
            ->init(
                $product,
                $imageId,
                ['type' => $imageType]
            )
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepTransparency(true)
            ->keepFrame(false);

        return $image->getUrl();
    }
}
