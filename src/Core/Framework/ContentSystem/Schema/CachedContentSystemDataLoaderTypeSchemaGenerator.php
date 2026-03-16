<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('framework')]
class CachedContentSystemDataLoaderTypeSchemaGenerator
{
    final public const CACHE_KEY = 'core_content_system_data_loader_type_schema';

    public function __construct(
        private readonly ContentSystemDataLoaderTypeSchemaGenerator $innerService,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array{sources: array<string, array{types: list<array{className: string, genericParameters: list<string>}>}>}
     */
    public function getSchema(): array
    {
        return $this->cache->get(self::CACHE_KEY, fn () => $this->innerService->getSchema());
    }
}
