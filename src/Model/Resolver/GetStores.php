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
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\InventoryInStorePickup\Model\SearchRequestBuilder;
use Magento\InventoryInStorePickup\Model\GetPickupLocations;

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
     * @var SearchRequestBuilder
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
     * GetStores constructor.
     * @param SearchRequestBuilder $searchRequest
     * @param GetPickupLocations $getPickupLocations
     * @param CountryFactory $countryFactory
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        SearchRequestBuilder $searchRequest,
        GetPickupLocations $getPickupLocations,
        CountryFactory $countryFactory,
        ScopeConfigInterface $scopeConfig
    ) {

        $this->searchRequest = $searchRequest;
        $this->getPickupLocations = $getPickupLocations;
        $this->countryFactory = $countryFactory;
        $this->scopeConfig = $scopeConfig;
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
        if (!isset($args['search']) || !isset($args['country'])) {
            throw new GraphQlInputException(
                __('Required parameter "search" or "country" is missing.')
            );
        }

        $result = [];

        $searchRequest = $this->searchRequest
            ->setAreaSearchTerm(sprintf(
                '%s:%s',
                $args['search'],
                $args['country']
            ))
            ->setAreaRadius($this->scopeConfig->getValue(self::SEARCH_RADIUS))
            ->setScopeCode('base')
            ->create();
        $searchResponse = $this->getPickupLocations->execute($searchRequest);

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
}
