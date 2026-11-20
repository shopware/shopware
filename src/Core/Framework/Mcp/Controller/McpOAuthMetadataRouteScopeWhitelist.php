<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RouteScopeWhitelistInterface;

/**
 * @internal
 */
#[Package('framework')]
class McpOAuthMetadataRouteScopeWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === McpOAuthMetadataController::class;
    }
}
