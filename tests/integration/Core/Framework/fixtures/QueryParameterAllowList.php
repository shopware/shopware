<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\fixtures;

/**
 * @internal
 */
class QueryParameterAllowList
{
    /**
     * @return array{
     *     groups: array<string, list<string>>,
     *     allowedList: array<string, list<string>>
     * }
     */
    public static function getQueryParameterAllowList(): array
    {
        $criteria = [
            'page',
            'limit',
            'term',
            'filter[]',
            'ids[]',
            'query',
            'associations',
            'post-filter[]',
            'sort[]',
            'aggregations[]',
            'fields[]',
            'grouping[]',
            'total-count-mode',
            'includes',
        ];

        return [
            'groups' => [
                'criteria' => $criteria,
                'product-listing' => array_merge(
                    $criteria,
                    [
                        'order',
                        'p',
                        'manufacturer',
                        'min-price',
                        'max-price',
                        'rating',
                        'shipping-free',
                        'properties',
                        'manufacturer-filter',
                        'price-filter',
                        'rating-filter',
                        'shipping-free-filter',
                        'property-filter',
                        'property-whitelist',
                        'reduce-aggregations',
                    ]
                ),
            ],
            'allowedList' => [
                '/store-api/product-listing/{categoryId}' => ['@product-listing'],
                '/store-api/search' => ['@product-listing'],
                '/store-api/search-suggest' => ['@product-listing'],
                '/store-api/cms/{id}' => ['@product-listing'],
                '/store-api/category' => ['@criteria'],
                '/store-api/category/{navigationId}' => ['@product-listing', 'slots'],
                '/store-api/country-state/{countryId}' => ['@criteria'],
                '/store-api/country' => ['@criteria'],
                '/store-api/currency' => ['@criteria'],
                '/store-api/language' => ['@criteria'],
                '/store-api/navigation/{activeId}/{rootId}' => ['@criteria', 'depth', 'buildTree'],
                '/store-api/payment-method' => ['@criteria'],
                '/store-api/product' => ['@criteria'],
                '/store-api/salutation' => ['@criteria'],
                '/store-api/seo-url' => ['@criteria'],
                '/store-api/shipping-method' => ['@criteria', 'onlyAvailable'],
                '/store-api/checkout/cart/line-item' => ['ids'],
                '/store-api/context/gateway' => ['data'],
            ],
        ];
    }
}
