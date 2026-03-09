<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[Package('framework')]
enum CartAction: string
{
    case Create = 'create';
    case Add = 'add';
    case Remove = 'remove';
    case Update = 'update';
    case Get = 'get';
}
