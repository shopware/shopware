<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:becomes-internal
 */
#[Package('core')]
interface CacheStateValidatorInterface
{
    public function isValid(Request $request, Response $response): bool;
}
