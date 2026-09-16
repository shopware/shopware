<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
interface MiddlewareInterface
{
    public function __invoke(callable $handler): callable;
}
