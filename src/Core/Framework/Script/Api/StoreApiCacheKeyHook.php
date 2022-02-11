<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Script\Execution\Awareness\OptionalFunctionHook;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;
use Shopware\Core\Framework\Script\Execution\FunctionHook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class StoreApiCacheKeyHook extends FunctionHook implements SalesChannelContextAware, StoppableHook, OptionalFunctionHook
{
    use StoppableHookTrait;

    public const FUNCTION_NAME = 'cache_key';

    private array $request;

    private array $query;

    private SalesChannelContext $salesChannelContext;

    private string $name;

    private ?string $cacheKey;

    public function __construct(string $name, array $request, array $query, SalesChannelContext $salesChannelContext)
    {
        $this->request = $request;
        $this->query = $query;
        $this->salesChannelContext = $salesChannelContext;

        parent::__construct($salesChannelContext->getContext());
        $this->name = $name;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

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
