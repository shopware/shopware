<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[Package('framework')]
enum CheckoutMethodType: string
{
    case Payment = 'payment';
    case Shipping = 'shipping';
    case All = 'all';
}
