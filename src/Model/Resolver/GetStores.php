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

namespace ScandiPWA\QuoteGraphQl\Model\Resolver;

use Exception;
use Magento\Directory\Model\Country\Postcode\ConfigInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\InventoryInStorePickup\Model\GetPickupLocations;
use Magento\InventoryInStorePickupApi\Api\Data\SearchRequestInterface;
use Magento\InventoryInStorePickupApi\Model\SearchRequestBuilderInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class GetStores
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class GetStores implements ResolverInterface
{
    /**
     * Config path to radius value (In magento original class it's private)
     */
    protected const SEARCH_RADIUS = 'carriers/instore/search_radius';

    /**
     * @var SearchRequestBuilderInterface
     */
    protected $searchRequest;

    /**
     * @var GetPickupLocations
     */
    protected $getPickupLocations;

    /**
     * @var CountryFactory
     */
    protected $countryFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected  $storeManager;

    /**
     * @var ConfigInterface
     */
    protected $postCodesConfig;

    /**
     * GetStores constructor.
     * @param SearchRequestBuilderInterface $searchRequest
     * @param GetPickupLocations $getPickupLocations
     * @param CountryFactory $countryFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ConfigInterface $postCodesConfig
     */
    public function __construct(
        SearchRequestBuilderInterface $searchRequest,
        GetPickupLocations $getPickupLocations,
        CountryFactory $countryFactory,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ConfigInterface $postCodesConfig
    ) {
        $this->searchRequest = $searchRequest;
        $this->getPickupLocations = $getPickupLocations;
        $this->countryFactory = $countryFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->postCodesConfig = $postCodesConfig;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field            $field
     * @param ContextInterface $context
     * @param ResolveInfo      $info
     * @param array|null       $value
     * @param array|null       $args
     * @return mixed|Value
     * @throws Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $search = $args['search'];
        $country = $args['country'];

        if (!isset($search) || !isset($country)) {
            throw new GraphQlInputException(
                __('Required parameter "search" or "country" is missing.')
            );
        }

        $result = [];

        if($args['search'] === '') {
            $searchRequest = $this->getAllStores();
        } else {
            $postCodes = $this->postCodesConfig->getPostCodes();

            if (!isset($postCodes[$country])) {
                throw new GraphQlInputException(__('No in-delivery support for provided country. Please select another country.'));
            }

            $searchRequest = $this->getStoresBySearch($search, $country);
        }

        try {
            $searchResponse = $this->getPickupLocations->execute($searchRequest);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        }

        foreach ($searchResponse->getItems() as $item) {
            $result[] = [
                'city' => $item->getCity(),
                'country' => $this->countryFactory->create()->loadByCode($item->getCountryId())->getName(),
                'description' => $item->getDescription(),
                'name' => $item->getName(),
                'phone' => $item->getPhone(),
                'pickup_location_code' => $item->getPickupLocationCode(),
                'postcode' => $item->getPostcode(),
                'region' => $item->getRegion(),
                'street' => $item->getStreet()
            ];
        }

        return ['stores' => $result];
    }

    /**
     * @return SearchRequestInterface
     * @throws LocalizedException
     */
    public function getAllStores() {
        $searchRequest = $this->searchRequest
            ->setScopeType(SalesChannelInterface::TYPE_WEBSITE)
            ->setScopeCode($this->storeManager->getWebsite()->getCode())
            ->setPageSize(1)
            ->create();

        return $searchRequest;
    }

    /**
     * @param $search
     * @param $country
     * @return SearchRequestInterface
     * @throws LocalizedException
     */
    public function getStoresBySearch($search, $country) {
        $searchRequest = $this->searchRequest
            ->setScopeType(SalesChannelInterface::TYPE_WEBSITE)
            ->setAreaSearchTerm(sprintf(
                '%s:%s',
                $search,
                $country
            ))
            ->setScopeCode($this->storeManager->getWebsite()->getCode())
            ->setAreaRadius((int) $this->scopeConfig->getValue(self::SEARCH_RADIUS))
            ->create();

        return $searchRequest;
    }
}
