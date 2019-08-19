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


use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Phrase;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Bundle\Model\Product\Type;
use Magento\Framework\DataObject;
use Magento\Catalog\Model\Product\Attribute\Repository;

/**
 * Class SaveCartItem
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class SaveCartItem implements ResolverInterface
{
    /**
     * @var CartItemRepositoryInterface
     */
    private $cartItemRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ParamOverriderCartId
     */
    protected $overriderCartId;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var Repository
     */
    protected $attributeRepository;
    
    /**
     * @var QuoteIdMask
     */
    protected $quoteIdMaskResource;
    
    /**
     * SaveCartItem constructor.
     * @param CartItemRepositoryInterface $cartItemRepository
     * @param QuoteIdMaskFactory          $quoteIdMaskFactory
     * @param CartRepositoryInterface     $quoteRepository
     * @param ParamOverriderCartId        $overriderCartId
     * @param ProductRepository           $productRepository
     * @param Repository                  $attributeRepository
     */
    public function __construct(
        CartItemRepositoryInterface $cartItemRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $quoteRepository,
        ParamOverriderCartId $overriderCartId,
        ProductRepository $productRepository,
        Repository $attributeRepository,
        QuoteIdMask $quoteIdMaskResource
    ) {
        $this->cartItemRepository = $cartItemRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteRepository = $quoteRepository;
        $this->overriderCartId = $overriderCartId;
        $this->productRepository = $productRepository;
        $this->attributeRepository = $attributeRepository;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
    }

    private function makeAddRequest(Product $product, array $options)
    {
        $data = [
            'product' => $product->getEntityId(),
            'qty' => $options['qty']
        ];

        switch ($product->getTypeId()) {
            case Configurable::TYPE_CODE:
                $data = $this->setConfigurableRequestOptions($options, $data);
                break;
            case Type::TYPE_CODE:
                $data = $this->setBundleRequestOptions($product, $data);
                break;
        }

        $request = new DataObject();
        $request->setData($data);

        return $request;
    }

    private function setConfigurableRequestOptions(array $options, array $data)
    {
        $configurableOptions = $options["product_option"]["extension_attributes"]["configurable_item_options"] ?? [];
        $superAttributes = [];

        foreach ($configurableOptions as $option) {
            $superAttributes[$option['option_id']] = $option['option_value'];
        }

        $data['super_attribute'] = $superAttributes;
        return $data;
    }

    private function setBundleRequestOptions(Product $product, array $data)
    {
        /** @var Type $typedProduct */
        $typedProduct = $product->getTypeInstance();

        $selectionCollection = $typedProduct->getSelectionsCollection($typedProduct->getOptionsIds($product), $product);

        $options = [];
        foreach ($selectionCollection as $proSelection) {
            $options[$proSelection->getOptionId()] = $proSelection->getSelectionId();
        }

        $data['bundle_option'] = $options;
        return $data;
    }
    
    protected function getGuestQuoteId(string $guestCardId): string
    {
        $quoteIdMask  = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResource->load($quoteIdMask, $guestCardId, 'masked_id');
        
        return $quoteIdMask->getQuoteId();
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param ContextInterface                                $context
     * @param ResolveInfo                                     $info
     * @param array|null                                      $value
     * @param array|null                                      $args
     * @return mixed|Value
     * @throws Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        $requestCartItem = $args['cartItem'];
        if (!$this->validateCartItem($requestCartItem)) {
            throw new GraphQlInputException(new Phrase('Cart item ID or product SKU must be passed'));
        }
        $quoteId = isset($args['guestCartId'])
            ? $this->getGuestQuoteId($args['guestCartId'])
            : $this->overriderCartId->getOverriddenValue();
        $quote = $this->quoteRepository->getActive($quoteId);
        
        $itemId = $this->getItemId($requestCartItem);
        ['qty' => $qty] = $requestCartItem;
        if ($itemId) {
            $cartItem = $quote->getItemById($itemId);
            $cartItem->setQty($qty);
            $this->cartItemRepository->save($cartItem);
        } else {
            $sku = $this->getSku($requestCartItem);
            $product = $this->productRepository->get($sku);
            if (!$product) {
                throw new GraphQlNoSuchEntityException(new Phrase('Product could not be loaded'));
            }
            $newQuoteItem = $this->buildQuoteItem($sku, $qty, (int)$quoteId,
                $requestCartItem['product_option'] ?? []);
            $quote->addProduct($product, $this->makeAddRequest(
                $product,
                $newQuoteItem
            ));
            $this->quoteRepository->save($quote);

            // Related to bug: https://github.com/magento/magento2/issues/2991
            $quote = $this->quoteRepository->getActive($quoteId);
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->quoteRepository->save($quote);
        }

        return [];
    }
    
    protected function buildQuoteItem(string $sku, int $qty, int $quoteId, array $options = []): array
    {
        return [
            'qty' => $qty,
            'sku' => $sku,
            'quote_id' => $quoteId,
            'product_options' => $options
        ];
    }
    
    /**
     * @param array $cartItem
     * @return bool
     */
    private function isIdStructUsed(array $cartItem): bool
    {
        return array_key_exists('id', $cartItem) && is_array($cartItem['id']);
    }
    
    /**
     * @param array $cartItem
     * @return int|null
     */
    protected function getItemId(array $cartItem): ?int {
        if (isset($cartItem['item_id'])) {
            return $cartItem['item_id'];
        }
        
        if ($this->isIdStructUsed($cartItem)) {
            return $this->getItemId($cartItem['id']);
        }
        
        return null;
    }
    
    /**
     * @param array $cartItem
     * @return string|null
     */
    protected function getSku(array $cartItem): ?string {
        if (isset($cartItem['sku'])) {
            return $cartItem['sku'];
        }
        
        if ($this->isIdStructUsed($cartItem)) {
            return $this->getSku($cartItem['id']);
        }
        
        return null;
    }
    
    /**
     * @param array $cartItem
     * @return bool
     */
    protected function validateCartItem(array $cartItem): bool
    {
        return isset($cartItem['item_id']) || isset($cartItem['sku']) || isset($cartItem['id']);
    }
}
