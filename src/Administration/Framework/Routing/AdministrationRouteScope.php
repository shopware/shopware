<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Routing;

use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Symfony\Component\HttpFoundation\Request;

class AdministrationRouteScope extends AbstractRouteScope
{
    /**
     * @var string[]
     */
    protected $allowedPaths = ['admin', 'api'];

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'administration';
    }
}
