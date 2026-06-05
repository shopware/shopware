<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\RetryMiddleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class RetryFailedStoreRequestMiddleware implements MiddlewareInterface
{
    private const NUMBER_OF_RETRIES_ON_503 = 3;

    public function __invoke(callable $handler): callable
    {
        $decider = function (int $retries, RequestInterface $request, ?ResponseInterface $response = null): bool {
            return $retries < self::NUMBER_OF_RETRIES_ON_503 && $response !== null && $response->getStatusCode() === 503;
        };

        $delay = function (int $retries, ?ResponseInterface $response = null, ?RequestInterface $request = null): int {
            return 2 ** ($retries - 1) * 5000;
        };

        return new RetryMiddleware($decider, $handler, $delay);
    }
}
