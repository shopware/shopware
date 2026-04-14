<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CachedSearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[CoversClass(CachedSearchConfigLoader::class)]
class CachedSearchConfigLoaderTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            ProductSearchConfigDefinition::ENTITY_NAME . '.written' => 'invalidate',
            ProductSearchConfigDefinition::ENTITY_NAME . '.deleted' => 'invalidate',
            ProductSearchConfigFieldDefinition::ENTITY_NAME . '.written' => 'invalidate',
            ProductSearchConfigFieldDefinition::ENTITY_NAME . '.deleted' => 'invalidate',
        ], CachedSearchConfigLoader::getSubscribedEvents());
    }

    public function testInvalidateClearsCachedConfig(): void
    {
        $decorated = $this->createMock(SearchConfigLoader::class);
        $decorated->expects($this->exactly(2))
            ->method('load')
            ->willReturn([[
                'and_logic' => '0',
                'strictness' => 50,
                'excluded_terms' => [],
                'min_search_length' => 2,
                'field' => 'name',
                'tokenize' => 1,
                'ranking' => 100.0,
            ]]);

        $loader = new CachedSearchConfigLoader($decorated, new ArrayAdapter());
        $context = Context::createDefaultContext();

        $loader->load($context);
        $loader->load($context);

        $loader->invalidate();
        $loader->load($context);
    }
}
