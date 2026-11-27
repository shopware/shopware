<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CachedSearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CachedSearchConfigLoader::class)]
class CachedSearchConfigLoaderTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $loadedLanguageIds = [];

    private SearchConfigLoader $decorated;

    private TagAwareAdapter $cache;

    private CachedSearchConfigLoader $loader;

    protected function setUp(): void
    {
        $decorated = static::createStub(SearchConfigLoader::class);
        $this->decorated = $decorated;
        $decorated->method('load')->willReturnCallback(function (Context $context): array {
            $this->loadedLanguageIds[] = $context->getLanguageId();

            return [[
                'and_logic' => '0',
                'excluded_terms' => [$context->getLanguageId()],
                'min_search_length' => 2,
                'field' => 'name',
                'tokenize' => 1,
                'ranking' => 700.0,
                'use_exact_subfield' => 1,
            ]];
        });

        $this->cache = new TagAwareAdapter(new ArrayAdapter(), new ArrayAdapter());
        $this->loader = new CachedSearchConfigLoader($decorated, $this->cache, static::createStub(CacheTagCollector::class));
    }

    public function testLoadsTheConfigOncePerLanguage(): void
    {
        $germanId = Uuid::randomHex();
        $german = new Context(new SystemSource(), [], Defaults::CURRENCY, [$germanId, Defaults::LANGUAGE_SYSTEM]);

        static::assertSame([Defaults::LANGUAGE_SYSTEM], $this->loader->load(Context::createDefaultContext())[0]['excluded_terms']);
        static::assertSame([$germanId], $this->loader->load($german)[0]['excluded_terms']);
        static::assertSame([Defaults::LANGUAGE_SYSTEM], $this->loader->load(Context::createDefaultContext())[0]['excluded_terms']);
        static::assertSame([$germanId], $this->loader->load($german)[0]['excluded_terms']);

        static::assertSame([Defaults::LANGUAGE_SYSTEM, $germanId], $this->loadedLanguageIds);
    }

    public function testTheCacheTagDropsTheConfigOfAllLanguages(): void
    {
        $germanId = Uuid::randomHex();
        $german = new Context(new SystemSource(), [], Defaults::CURRENCY, [$germanId, Defaults::LANGUAGE_SYSTEM]);

        $this->loader->load(Context::createDefaultContext());
        $this->loader->load($german);
        $this->loadedLanguageIds = [];

        $this->cache->invalidateTags([CachedSearchConfigLoader::CACHE_KEY]);
        $this->loader->load(Context::createDefaultContext());
        $this->loader->load($german);

        static::assertSame([Defaults::LANGUAGE_SYSTEM, $germanId], $this->loadedLanguageIds);
    }

    public function testAddsTheCacheTagToTheRequestOnEveryLoad(): void
    {
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->exactly(2))
            ->method('addTag')
            ->with(CachedSearchConfigLoader::CACHE_KEY);

        $loader = new CachedSearchConfigLoader($this->decorated, $this->cache, $cacheTagCollector);
        $loader->load(Context::createDefaultContext());
        $loader->load(Context::createDefaultContext());

        static::assertSame([Defaults::LANGUAGE_SYSTEM], $this->loadedLanguageIds);
    }
}
