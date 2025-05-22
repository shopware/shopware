<?php

declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Util\DateHistogramCase;

return [
    [
        new DateHistogramCase(DateHistogramAggregation::PER_MINUTE, [
            '2019-01-01 10:11:00' => 1,
            '2019-01-01 10:13:00' => 1,
            '2019-06-15 13:00:00' => 1,
            '2020-09-30 15:00:00' => 1,
            '2021-12-10 11:59:00' => 2,
            '2024-12-11 23:59:00' => 1,
        ]),
    ],
    [
        new DateHistogramCase(DateHistogramAggregation::PER_HOUR, [
            '2019-01-01 10:00:00' => 2,
            '2019-06-15 13:00:00' => 1,
            '2020-09-30 15:00:00' => 1,
            '2021-12-10 11:00:00' => 2,
            '2024-12-11 23:00:00' => 1,
        ]),
    ],
    [
        new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
            '2019-01-01 00:00:00' => 2,
            '2019-06-15 00:00:00' => 1,
            '2020-09-30 00:00:00' => 1,
            '2021-12-10 00:00:00' => 2,
            '2024-12-11 00:00:00' => 1,
        ]),
    ],
    [
        new DateHistogramCase(DateHistogramAggregation::PER_WEEK, [
            '2018 01' => 2,
            '2019 24' => 1,
            '2020 40' => 1,
            '2021 49' => 2,
            '2024 50' => 1,
        ]),
    ],
    [
        new DateHistogramCase(DateHistogramAggregation::PER_MONTH, [
            '2019-01-01 00:00:00' => 2,
            '2019-06-01 00:00:00' => 1,
            '2020-09-01 00:00:00' => 1,
            '2021-12-01 00:00:00' => 2,
            '2024-12-01 00:00:00' => 1,
        ]),
    ],
    [
        new DateHistogramCase(DateHistogramAggregation::PER_QUARTER, [
            '2019 1' => 2,
            '2019 2' => 1,
            '2020 3' => 1,
            '2021 4' => 2,
            '2024 4' => 1,
        ]),
    ],
    [
        new DateHistogramCase(DateHistogramAggregation::PER_YEAR, [
            '2019-01-01 00:00:00' => 3,
            '2020-01-01 00:00:00' => 1,
            '2021-01-01 00:00:00' => 2,
            '2024-01-01 00:00:00' => 1,
        ]),
    ],
    [
        new DateHistogramCase(DateHistogramAggregation::PER_MONTH, [
            '2019 January' => 2,
            '2019 June' => 1,
            '2020 September' => 1,
            '2021 December' => 2,
            '2024 December' => 1,
        ], 'Y F'),
    ],
    [
        new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
            'Tuesday 01st Jan, 2019' => 2,
            'Saturday 15th Jun, 2019' => 1,
            'Wednesday 30th Sep, 2020' => 1,
            'Friday 10th Dec, 2021' => 2,
            'Wednesday 11th Dec, 2024' => 1,
        ], 'l dS M, Y'),
    ],
    [
        new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
            '2019-01-01 00:00:00' => 2,
            '2019-06-15 00:00:00' => 1,
            '2020-09-30 00:00:00' => 1,
            '2021-12-10 00:00:00' => 2,
            '2024-12-12 00:00:00' => 1,
        ], null, 'Europe/Berlin'),
    ],
    // case with time zone alias
    [
        new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
            '2019-01-01 00:00:00' => 2,
            '2019-06-15 00:00:00' => 1,
            '2020-09-30 00:00:00' => 1,
            '2021-12-10 00:00:00' => 2,
            '2024-12-12 00:00:00' => 1,
        ], null, 'Asia/Ho_Chi_Minh'),
    ],
];
