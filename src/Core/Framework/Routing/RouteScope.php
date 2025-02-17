<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class RouteScope extends AbstractRouteScope
{
    /**
     * @var list<string>
     */
    protected array $allowedPaths = ['_wdt', '_profiler', '_error'];

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'default';
    }
}
