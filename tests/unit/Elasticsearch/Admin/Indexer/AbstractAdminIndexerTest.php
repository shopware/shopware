<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;

#[CoversClass(AbstractAdminIndexer::class)]
class AbstractAdminIndexerTest extends TestCase
{
    public function testGetSupportedSearchFieldsHandlesNestedProperties(): void
    {
        $languageId = Uuid::randomHex();

        $indexer = new class($languageId) extends AbstractAdminIndexer {
            public function __construct(private readonly string $languageId)
            {
            }

            public function getDecorated(): AbstractAdminIndexer
            {
                throw new RuntimeException('not required in test');
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
                throw new RuntimeException('not required in test');
            }

            public function fetch(array $ids): array
            {
                throw new RuntimeException('not required in test');
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
                        'mediaFolder' => [
                            'properties' => [
                                'defaultFolder' => [
                                    'properties' => [
                                        'entity' => ['type' => 'keyword'],
                                    ],
                                ],
                            ],
                        ],
                        'title' => [
                            'properties' => [
                                $this->languageId => ['type' => 'keyword'],
                            ],
                        ],
                    ],
                ];
            }
        };

        $fields = $indexer->getSupportedSearchFields();

        static::assertContains('mediaFolder.defaultFolder.entity', $fields);
        static::assertContains('test_entity.mediaFolder.defaultFolder.entity', $fields);
        static::assertContains('title', $fields);
        static::assertContains('test_entity.title', $fields);
    }
}
