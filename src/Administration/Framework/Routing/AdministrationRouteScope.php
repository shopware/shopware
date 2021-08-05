<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Routing;

use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Shopware\Core\Framework\Routing\ApiContextRouteScopeDependant;
use Symfony\Component\HttpFoundation\Request;

class AdministrationRouteScope extends AbstractRouteScope implements ApiContextRouteScopeDependant
{
    public const ID = 'administration';

    /**
     * @var string[]
     */
    protected $allowedPaths;

    public function __construct(string $administrationPathName = 'admin')
    {
        $this->allowedPaths = [$administrationPathName, 'api'];
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
