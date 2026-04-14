<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @final
 *
 * @phpstan-import-type SearchConfig from SearchConfigLoader
 */
#[Package('framework')]
class CachedSearchConfigLoader extends SearchConfigLoader implements EventSubscriberInterface
{
    final public const CACHE_KEY = 'search-config';

    /**
     * @internal
     */
    public function __construct(
        private readonly SearchConfigLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return array<SearchConfig>
     */
    public function load(Context $context): array
    {
        return $this->cache->get(self::CACHE_KEY, fn (): array => $this->decorated->load($context));
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductSearchConfigDefinition::ENTITY_NAME . '.written' => 'invalidate',
            ProductSearchConfigDefinition::ENTITY_NAME . '.deleted' => 'invalidate',
            ProductSearchConfigFieldDefinition::ENTITY_NAME . '.written' => 'invalidate',
            ProductSearchConfigFieldDefinition::ENTITY_NAME . '.deleted' => 'invalidate',
        ];
    }

    public function invalidate(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
