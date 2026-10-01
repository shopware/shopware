<?php

declare(strict_types=1);

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Test\TestDefaults;

return [
    [
        new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
            ]
        ),
        ['s-1', 's-2', 's-3'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_ALL),
            ]
        ),
        ['s-1', 's-4'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
                new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_LINK),
            ]
        ),
        ['s-2'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
                new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_SEARCH),
            ]
        ),
        ['s-3'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
                        new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_ALL),
                    ]
                ),
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.salesChannelId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                        new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_LINK),
                    ]
                ),
            ]
        ),
        ['s-1', 's-3'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_XOR,
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
                        new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_SEARCH),
                    ]
                ),
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.salesChannelId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                        new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_SEARCH),
                    ]
                ),
            ]
        ),
        ['s-2', 's-3'],
    ],
];
