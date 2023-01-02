<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Log\Package;
use Psr\Http\Message\ResponseInterface;

/**
 * @package merchant-services
 *
 * @internal
 */
#[Package('merchant-services')]
interface MiddlewareInterface
{
    public function __invoke(ResponseInterface $response): ResponseInterface;
}
