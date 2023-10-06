<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;
use Shopware\Core\Framework\Script\Execution\OptionalFunctionHook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Triggered when the api endpoint /store-api/script/{hook} is called. Used to provide a cache-key based on the request.
 * Needs to be implemented when your store-api route should be cached.
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 *
 * @final
 */
#[Package('core')]
class StoreApiCacheKeyHook extends OptionalFunctionHook implements SalesChannelContextAware, StoppableHook
{
    use StoppableHookTrait;

    final public const FUNCTION_NAME = 'cache_key';

    private ?string $cacheKey = null;

    public function __construct(
        private readonly string $name,
        /**
         * @var array<string, mixed>
         */
        private readonly array $request,
        /**
         * @var array<string, mixed>
         */
        private readonly array $query,
        private readonly SalesChannelContext $salesChannelContext
    ) {
        parent::__construct($salesChannelContext->getContext());
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getCacheKey(): ?string
    {
        return $this->cacheKey;
    }

    public function setCacheKey(string $key): void
    {
        $this->cacheKey = $key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public static function getServiceIds(): array
    {
        // No service access allowed for generating the cache key
        return [];
    }

    public function getFunctionName(): string
    {
        return self::FUNCTION_NAME;
    }
}
