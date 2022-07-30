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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\QuoteGraphQl\Model\Cart\GetCartProducts;
use Magento\QuoteGraphQl\Model\Resolver\CartItems as SourceCartItems;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

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
     * @var DataPostProcessor
     */
    protected DataPostProcessor $productPostProcessor;

    /**
     * @param GetCartProducts $getCartProducts
     * @param Uid $uidEncoder
     * @param Emulation $emulation
     * @param HelperImage $helperImage
     * @param StoreManagerInterface $storeManager
     * @param DataPostProcessor $productPostProcessor
     */
    public function __construct(
        GetCartProducts $getCartProducts,
        Uid $uidEncoder,
        Emulation $emulation,
        HelperImage $helperImage,
        StoreManagerInterface $storeManager,
        DataPostProcessor $productPostProcessor
    ) {
        parent::__construct(
            $getCartProducts,
            $uidEncoder
        );

        $this->getCartProducts = $getCartProducts;
        $this->uidEncoder = $uidEncoder;
        $this->emulation = $emulation;
        $this->helperImage = $helperImage;
        $this->storeManager = $storeManager;
        $this->productPostProcessor = $productPostProcessor;
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
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $cart = $value['model'];
        $result = [];

        if ($cart->getData('has_error')) {
            $errors = $cart->getErrors();

            foreach ($errors as $error) {
                $result[] = new GraphQlInputException(__($error->getText()));
            }
        }

        $cartProductsData = $this->getCartProductsData($cart, $info);
        $cartItems = $cart->getAllVisibleItems();

        $storeId = $this->storeManager->getStore()->getId();
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        /** @var QuoteItem $cartItem */
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            $productId = $product->getId();

            if (!isset($cartProductsData[$productId])) {
                $result[] = new GraphQlNoSuchEntityException(
                    __("The product that was requested doesn't exist. Verify the product and try again.")
                );

                continue;
            }

            $productData = array_merge($cartProductsData[$productId],
                [
                    'thumbnail' =>
                        [
                            'path' => $product->getThumbnail(),
                            'url' => $this->getImageUrl('thumbnail', $product->getThumbnail(), $product)
                        ]
                ]
            );

            $result[] = [
                'id' => $cartItem->getItemId(),
                'sku' => $cartItem->getSku(),
                'uid' => $this->uidEncoder->encode((string) $cartItem->getItemId()),
                'quantity' => $cartItem->getQty(),
                'product' => $productData,
                'model' => $cartItem,
            ];
        }

        $this->emulation->stopEnvironmentEmulation();

        return $result;
    }

    /**
     * Get product data for cart items
     *
     * @param Quote $cart
     * @param ResolveInfo $info
     * @return array
     */
    public function getCartProductsData(Quote $cart, ResolveInfo $info): array
    {
        $products = $this->getCartProducts->execute($cart);
        $productsData = [];

        $productsPostData = $this->productPostProcessor->process(
            $products,
            'items/product',
            $info,
            ['isCartProduct'=> true]
        );

        foreach ($products as $product) {
            $productId = $product->getId();

            $productsData[$productId] = $productsPostData[$productId];
            $productsData[$productId]['model'] = $product;
            $productsData[$productId]['uid'] = $this->uidEncoder->encode((string)$productId);
        }

        return $productsData;
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
