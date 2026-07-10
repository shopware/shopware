<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Shopware\Storefront\Controller\StorybookController;

/**
 * @internal
 */
#[Package('framework')]
final class StorybookRouteScopeAllowList implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === StorybookController::class;
    }
}
