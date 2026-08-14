<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-14 09:43:20
 */

namespace Shopware\Core\Framework\Api\Request\StoreApi;

use Shopware\Core\Framework\Log\Package;

/**
 * Whether the total for the total number of hits should be determined for the search query. none = disabled total count, exact = calculate exact total amount (slow), next-pages = calculate only for next page (fast)
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum TotalCountMode: string
{
    case NONE = 'none';
    case EXACT = 'exact';
    case NEXT_PAGES = 'next-pages';
}
