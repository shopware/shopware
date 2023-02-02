<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

interface RouteScopeWhitelistInterface
{
    /**
     * return true, the supplied controller is whitelisted, false if scope matching should be applied
     */
    public function applies(string $controllerClass): bool;
}
