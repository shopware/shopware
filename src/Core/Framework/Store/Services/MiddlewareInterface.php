<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Psr\Http\Message\ResponseInterface;

/**
 * @package merchant-services
 *
 * @internal
 */
interface MiddlewareInterface
{
    public function __invoke(ResponseInterface $response): ResponseInterface;
}
