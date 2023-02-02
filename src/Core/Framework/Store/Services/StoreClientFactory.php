<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class StoreClientFactory
{
    private const CONFIG_KEY_STORE_API_URI = 'core.store.apiUri';

    private SystemConfigService $configService;

    /**
     * @var MiddlewareInterface[]
     */
    private iterable $middlewares;

    /**
     * @internal
     */
    public function __construct(
        SystemConfigService $configService,
        iterable $middlewares
    ) {
        $this->configService = $configService;
        $this->middlewares = $middlewares;
    }

    public function create(): Client
    {
        $stack = HandlerStack::create();

        foreach ($this->middlewares as $middleware) {
            $stack->push(Middleware::mapResponse($middleware));
        }

        $config = $this->getClientBaseConfig();
        $config['handler'] = $stack;

        return new Client($config);
    }

    private function getClientBaseConfig(): array
    {
        return [
            'base_uri' => $this->configService->get(self::CONFIG_KEY_STORE_API_URI),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/vnd.api+json,application/json',
            ],
        ];
    }
}
