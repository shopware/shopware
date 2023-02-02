<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Routing\KnownIps;

use Symfony\Component\HttpFoundation\Request;

interface KnownIpsCollectorInterface
{
    public function collectIps(Request $request): array;
}
