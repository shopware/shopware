<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Shopware\Elasticsearch\Admin\AdminElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;

/**
 * @internal
 */
#[CoversClass(AdminElasticsearchEntitySearcher::class)]
class AdminElasticsearchEntitySearcherTest extends TestCase
{
    public function testFallsBackWhenFeatureDisabled(): void
    {
        $decorated = $this->createMock(EntitySearcherInterface::class);
        $registry = $this->createMock(AdminSearchRegistry::class);
        $helper = $this->createMock(AdminElasticsearchHelper::class);
        $searcher = $this->createMock(AdminSearcher::class);

        $criteria = new Criteria();
        $context = new Context(new AdminApiSource('test', Uuid::randomHex()));
        $definition = $this->createDefinition();

        $expected = new IdSearchResult(0, [], $criteria, $context);

        $decorated->expects($this->once())
            ->method('search')
            ->with($definition, $criteria, $context)
            ->willReturn($expected);

        $searcher->expects($this->never())->method('searchIds');

        Feature::fake([], static function () use ($decorated, $registry, $helper, $searcher, $definition, $criteria, $context, $expected): void {
            $entitySearcher = new AdminElasticsearchEntitySearcher(
                $decorated,
                $registry,
                $helper,
                $searcher,
            );

            $result = $entitySearcher->search($definition, $criteria, $context);

            static::assertSame($expected, $result);
        });
    }

    public function testUsesAdminSearchWhenAllowed(): void
    {
        $decorated = $this->createMock(EntitySearcherInterface::class);
        $registry = $this->createMock(AdminSearchRegistry::class);
        $helper = $this->createMock(AdminElasticsearchHelper::class);
        $searcher = $this->createMock(AdminSearcher::class);

        $criteria = (new Criteria())->setTerm('search');
        $context = new Context(new AdminApiSource('test', Uuid::randomHex()));
        $definition = $this->createDefinition();
        $indexer = $this->createIndexer();

        $helper->method('isEnabled')->willReturn(true);

        $registry->method('hasIndexer')->with($definition->getEntityName())->willReturn(true);
        $registry->method('getIndexer')->with($definition->getEntityName())->willReturn($indexer);

        $expected = new IdSearchResult(1, ['abc' => ['primaryKey' => 'abc', 'data' => ['id' => 'abc']]], $criteria, $context);

        $searcher->expects($this->once())
            ->method('searchIds')
            ->with($definition->getEntityName(), $criteria, $context)
            ->willReturn($expected);

        $decorated->expects($this->never())->method('search');

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], static function () use ($decorated, $registry, $helper, $searcher, $definition, $criteria, $context, $expected): void {
            $entitySearcher = new AdminElasticsearchEntitySearcher(
                $decorated,
                $registry,
                $helper,
                $searcher,
            );

            $result = $entitySearcher->search($definition, $criteria, $context);

            static::assertSame($expected, $result);
        });
    }

    private function createDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'test_entity';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection();
            }
        };
    }

    private function createIndexer(): AbstractAdminIndexer
    {
        return new class extends AbstractAdminIndexer {
            public function getDecorated(): AbstractAdminIndexer
            {
                throw new DecorationPatternException(self::class);
            }

            public function getName(): string
            {
                return 'test-indexer';
            }

            public function getEntity(): string
            {
                return 'test_entity';
            }

            public function getIterator(): IterableQuery
            {
                return new LastIdQuery(new QueryBuilder(new FakeConnection([])));
            }

            public function fetch(array $ids): array
            {
                return [];
            }

            public function globalData(array $result, Context $context): array
            {
                return [
                    'total' => 0,
                    'data' => new EntityCollection([]),
                ];
            }

            public function mapping(array $mapping): array
            {
                return [
                    'properties' => [
                        'text' => ['type' => 'keyword'],
                    ],
                ];
            }
        };
    }
}
