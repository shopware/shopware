<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Shopware\Storefront\Controller\RobotsController;

#[Package('framework')]
class RobotsRouteScopeWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === RobotsController::class;
    }
}
