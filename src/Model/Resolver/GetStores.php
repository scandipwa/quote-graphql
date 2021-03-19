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
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\InventoryInStorePickup\Model\SearchRequestBuilder;
use Magento\InventoryInStorePickup\Model\GetPickupLocations;

/**
 * Class SaveCartItem
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class GetStores implements ResolverInterface
{
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
     * GetStores constructor.
     * @param SearchRequestBuilder $searchRequest
     * @param GetPickupLocations $getPickupLocations
     * @param CountryFactory $countryFactory
     */
    public function __construct(
        SearchRequestBuilder $searchRequest,
        GetPickupLocations $getPickupLocations,
        CountryFactory $countryFactory
    ) {

        $this->searchRequest = $searchRequest;
        $this->getPickupLocations = $getPickupLocations;
        $this->countryFactory = $countryFactory;
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
    )
    {
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
            ->setAreaRadius(200)
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
