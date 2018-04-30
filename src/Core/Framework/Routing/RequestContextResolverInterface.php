<?php declare(strict_types=1);

namespace Shopware\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

interface RequestContextResolverInterface
{
    public function resolve(Request $master, Request $request): void;
}
