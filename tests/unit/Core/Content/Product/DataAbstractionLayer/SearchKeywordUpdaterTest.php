<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeywordCollection;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(SearchKeywordUpdater::class)]
class SearchKeywordUpdaterTest extends TestCase
{
    public function testDisabledIndexingSkipsUpdate(): void
    {
        $languageRepository = $this->createMock(EntityRepository::class);
        $productRepository = $this->createMock(EntityRepository::class);
        $analyzer = $this->createMock(ProductSearchKeywordAnalyzerInterface::class);

        $languageRepository->expects($this->never())->method('search');
        $productRepository->expects($this->never())->method('search');
        $analyzer->expects($this->never())->method('analyze');

        $updater = new SearchKeywordUpdater(
            $this->createMock(Connection::class),
            $languageRepository,
            $productRepository,
            $analyzer,
            false
        );

        $updater->update(['f70db8f6eb884b1ea2a691da3f74dc93'], Context::createDefaultContext());
    }

    public function testBuildCriteriaSkipsParentAssociation(): void
    {
        $definition = $this->createCompiledProductDefinition();

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($definition);

        $updater = new SearchKeywordUpdater(
            $this->createMock(Connection::class),
            $this->createMock(EntityRepository::class),
            $productRepository,
            $this->createMock(ProductSearchKeywordAnalyzerInterface::class),
        );

        $criteria = new Criteria();

        $buildCriteria = new \ReflectionMethod($updater, 'buildCriteria');
        $buildCriteria->setAccessible(true);
        $buildCriteria->invoke($updater, ['parent.name'], $criteria, Context::createDefaultContext());

        static::assertFalse($criteria->hasAssociation('parent'));
    }

    public function testUpdateLanguageHydratesParentProductsWhenParentNameIsConfigured(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            static::markTestSkipped('This test requires the pdo_sqlite extension');
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $connection->executeStatement(
            'CREATE TABLE product_search_keyword (product_id BLOB, language_id BLOB, version_id BLOB)'
        );

        $childId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $child = new ProductEntity();
        $child->setId($childId);
        $child->setParentId($parentId);

        $parent = new ProductEntity();
        $parent->setId($parentId);

        $definition = $this->createCompiledProductDefinition();

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($definition);

        $searchCalls = 0;
        $productRepository->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $searchContext) use (&$searchCalls, $parent): EntitySearchResult {
                ++$searchCalls;

                if ($searchCalls === 1) {
                    static::assertFalse($criteria->hasAssociation('parent'));

                    return new EntitySearchResult(
                        ProductDefinition::ENTITY_NAME,
                        0,
                        new ProductCollection(),
                        null,
                        $criteria,
                        $searchContext
                    );
                }

                if ($searchCalls === 2) {
                    return new EntitySearchResult(
                        ProductDefinition::ENTITY_NAME,
                        1,
                        new ProductCollection([$parent]),
                        null,
                        $criteria,
                        $searchContext
                    );
                }

                return new EntitySearchResult(
                    ProductDefinition::ENTITY_NAME,
                    0,
                    new ProductCollection(),
                    null,
                    $criteria,
                    $searchContext
                );
            }
        );

        $analyzer = $this->createMock(ProductSearchKeywordAnalyzerInterface::class);
        $analyzer->expects($this->once())
            ->method('analyze')
            ->with(
                static::callback(static function (ProductEntity $product) use ($parentId): bool {
                    return $product->getParent()?->getId() === $parentId;
                }),
                static::identicalTo($context),
                [['field' => 'parent.name', 'tokenize' => '1', 'ranking' => '100', 'language_id' => Defaults::LANGUAGE_SYSTEM]]
            )
            ->willReturn(new AnalyzedKeywordCollection());

        $updater = new SearchKeywordUpdater(
            $connection,
            $this->createMock(EntityRepository::class),
            $productRepository,
            $analyzer,
        );

        $config = new \ReflectionProperty($updater, 'config');
        $config->setAccessible(true);
        $config->setValue($updater, [
            Defaults::LANGUAGE_SYSTEM => [[
                'field' => 'parent.name',
                'tokenize' => '1',
                'ranking' => '100',
                'language_id' => Defaults::LANGUAGE_SYSTEM,
            ]],
        ]);

        $updateLanguage = new \ReflectionMethod($updater, 'updateLanguage');
        $updateLanguage->setAccessible(true);

        /** @var array<string, ProductEntity> $result */
        $result = $updateLanguage->invoke($updater, [$childId], $context, [$childId => $child]);

        static::assertArrayHasKey($childId, $result);
        static::assertSame($parentId, $result[$childId]->getParent()?->getId());
    }

    private function createCompiledProductDefinition(): ProductDefinition
    {
        $definition = new ProductDefinition();
        $definition->compile(KernelLifecycleManager::getKernel()->getContainer()->get(DefinitionInstanceRegistry::class));

        return $definition;
    }
}
