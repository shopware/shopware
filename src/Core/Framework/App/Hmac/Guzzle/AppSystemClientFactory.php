<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Hmac\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppSystemClientFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AuthMiddleware $authMiddleware,
    ) {
    }

    /**
     * @param iterable<callable> $middlewares
     */
    public function create(
        int $timeout = 5,
        int $connectTimeout = 1,
        ?callable $handler = null,
        iterable $middlewares = [],
    ): ClientInterface {
        $stack = HandlerStack::create($handler);
        $stack->push($this->authMiddleware);

        foreach ($middlewares as $middleware) {
            $stack->push($middleware);
        }

        return new Client([
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'handler' => $stack,
        ]);
    }
}
