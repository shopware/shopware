<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

class RouteScope extends AbstractRouteScope
{
    /**
     * @var string[]
     */
    protected $allowedPaths = ['_wdt', '_profiler', '_error'];

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'default';
    }
}
