<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Shopware\Core\Profiling\Controller\ProfilerController;

#[Package('core')]
class ProfilerWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === ProfilerController::class;
    }
}
