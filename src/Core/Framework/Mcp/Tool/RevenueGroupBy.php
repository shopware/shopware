<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[Package('framework')]
enum RevenueGroupBy: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
}
