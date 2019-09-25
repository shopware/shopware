<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface CacheStateValidatorInterface
{
    public function isValid(Request $request, Response $response): bool;
}
