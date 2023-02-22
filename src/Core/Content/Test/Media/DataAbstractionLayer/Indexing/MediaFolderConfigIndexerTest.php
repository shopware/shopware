<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MediaFolderConfigIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $folderRepository;

    private Context $context;

    private Connection $connection;

    private MediaFolderIndexer $configIndexer;

    protected function setUp(): void
    {
        $this->folderRepository = $this->getContainer()->get('media_folder.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->configIndexer = $this->getContainer()->get(MediaFolderIndexer::class);
        $this->context = Context::createDefaultContext();
    }

    public function testOnRefreshItUpdatesChildConfig(): void
    {
        $ids = new TestDataCollection();

        $this->folderRepository->create([
            [
                'id' => $ids->create('parent'),
                'name' => 'Parent',
                'configuration' => [
                    'id' => $ids->create('config-1'),
                    'createThumbnails' => true,
                ],
                'children' => [
                    [
                        'id' => $ids->create('child-1'),
                        'name' => 'child',
                        'useParentConfiguration' => true,
                        'configurationId' => $ids->get('config-1'),
                        'children' => [
                            [
                                'id' => $ids->create('child-1-1'),
                                'name' => 'child 1.1',
                                'useParentConfiguration' => true,
                                'configurationId' => $ids->get('config-1'),
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->context);

        $updatedId = $ids->create('config-2');

        $this->folderRepository->update([
            [
                'id' => $ids->get('parent'),
                'configuration' => [
                    'id' => $updatedId,
                    'createThumbnails' => false,
                ],
            ],
        ], $this->context);

        $children = $this->folderRepository->search(new Criteria($ids->getList(['child-1', 'child-1-1'])), $this->context);

        static::assertEquals($updatedId, $children->get($ids->get('child-1'))->getConfigurationId());
        static::assertEquals($updatedId, $children->get($ids->get('child-1-1'))->getConfigurationId());
    }

    public function testOnRefreshItUpdatesOwnConfig(): void
    {
        $parentId = Uuid::randomHex();
        $child1Id = Uuid::randomHex();
        $child1_1Id = Uuid::randomHex();
        $child1_1_1Id = Uuid::randomHex();
        $configId = Uuid::randomHex();
        $childConfigId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentId,
                'name' => 'Parent',
                'configuration' => [
                    'id' => $configId,
                    'createThumbnails' => true,
                ],
                'children' => [
                    [
                        'id' => $child1Id,
                        'name' => 'child',
                        'useParentConfiguration' => false,
                        'configuration' => [
                            'id' => $childConfigId,
                            'createThumbnails' => true,
                        ],
                        'children' => [
                            [
                                'id' => $child1_1Id,
                                'name' => 'child 1.1',
                                'useParentConfiguration' => true,
                                'configurationId' => $childConfigId,
                                'children' => [
                                    [
                                        'id' => $child1_1_1Id,
                                        'name' => 'child 1.1.1',
                                        'useParentConfiguration' => true,
                                        'configurationId' => $childConfigId,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->context);

        $this->folderRepository->update([
            [
                'id' => $child1_1Id,
                'parentId' => $parentId,
            ],
        ], $this->context);

        $children = $this->folderRepository->search(new Criteria([$child1_1Id, $child1_1_1Id]), $this->context);

        static::assertEquals($configId, $children->get($child1_1Id)->getConfigurationId());
        static::assertEquals($configId, $children->get($child1_1_1Id)->getConfigurationId());
    }

    public function testIndex(): void
    {
        $parentId = Uuid::randomHex();
        $child1Id = Uuid::randomHex();
        $child1_1Id = Uuid::randomHex();
        $configId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentId,
                'name' => 'Parent',
                'useParentConfiguration' => false,
                'configuration' => [
                    'id' => $configId,
                    'createThumbnails' => true,
                ],
                'children' => [
                    [
                        'id' => $child1Id,
                        'name' => 'child',
                        'useParentConfiguration' => true,
                        'configurationId' => $configId,
                        'children' => [
                            [
                                'id' => $child1_1Id,
                                'name' => 'child 1.1',
                                'useParentConfiguration' => true,
                                'configurationId' => $configId,
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->context);

        $this->connection->createQueryBuilder()
            ->update('media_folder')
            ->set('media_folder_configuration_id', ':configId')
            ->andWhere('id in (:ids)')
            ->setParameter('configId', Uuid::randomBytes())
            ->setParameter(
                'ids',
                [Uuid::fromHexToBytes($child1Id), Uuid::fromHexToBytes($child1_1Id)],
                ArrayParameterType::STRING
            )
            ->executeStatement();

        $message = $this->configIndexer->iterate(['offset' => 0]);
        $this->configIndexer->handle($message);

        $children = $this->folderRepository->search(new Criteria([$child1Id, $child1_1Id]), $this->context);

        static::assertEquals($configId, $children->get($child1Id)->getConfigurationId());
        static::assertEquals($configId, $children->get($child1_1Id)->getConfigurationId());
    }
}
