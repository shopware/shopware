<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Shopware\Core\Framework\Routing\ApiContextRouteScopeDependant;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class AdministrationRouteScope extends AbstractRouteScope implements ApiContextRouteScopeDependant
{
    final public const ID = 'administration';

    /**
     * @internal
     */
    public function __construct(string $administrationPathName = 'admin')
    {
        $this->allowedPaths = [$administrationPathName, ApiRouteScope::ID];
    }

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return self::ID;
    }
}
