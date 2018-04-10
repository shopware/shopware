<?php

namespace Shopware\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

interface RequestContextResolverInterface
{
    public const CONTEXT_REQUEST_ATTRIBUTE = 'x-sw-context';

    public function resolve(Request $master, Request $request): void;
}