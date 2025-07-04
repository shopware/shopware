<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
class StoreClientFactory
{
    private const CONFIG_KEY_STORE_API_URI = 'core.store.apiUri';

    public function __construct(
        private readonly SystemConfigService $configService
    ) {
    }

    /**
     * @param MiddlewareInterface[] $middlewares
     */
    public function create(iterable $middlewares = []): ClientInterface
    {
        $stack = HandlerStack::create();

        foreach ($middlewares as $middleware) {
            $stack->push(self::mapResponse($middleware));
        }

        $config = $this->getClientBaseConfig();
        $config['handler'] = $stack;

        return new Client($config);
    }

    /**
     * @return array{base_uri: string, headers: array<string, string>}
     */
    private function getClientBaseConfig(): array
    {
        return [
            'base_uri' => $this->configService->getString(self::CONFIG_KEY_STORE_API_URI),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/vnd.api+json,application/json',
            ],
        ];
    }

    /**
     * Similar to {@see Middleware::mapResponse} but does also include the request.
     *
     * @param callable(ResponseInterface, RequestInterface): ResponseInterface $fn
     */
    private static function mapResponse(callable $fn): callable
    {
        return static function (callable $handler) use ($fn): callable {
            /** @var callable(RequestInterface, array<mixed>): Promise $handler */
            return static function (RequestInterface $request, array $options) use ($handler, $fn) {
                return $handler($request, $options)->then(fn ($response) => $fn($response, $request));
            };
        };
    }
}
