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
    final public const ALLOWED_PATH = 'admin';

    /**
     * @internal
     */
    public function __construct(string $administrationPathName = self::ALLOWED_PATH)
    {
        $this->allowedPaths = [$administrationPathName, ApiRouteScope::ALLOWED_PATH];
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
