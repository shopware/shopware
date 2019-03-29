<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderConfigIndexer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class MediaFolderConfigIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $folderRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Connection
     */
    private $conncetion;

    /**
     * @var MediaFolderConfigIndexer
     */
    private $configIndexer;

    protected function setUp(): void
    {
        $this->folderRepository = $this->getContainer()->get('media_folder.repository');
        $this->conncetion = $this->getContainer()->get(Connection::class);
        $this->configIndexer = $this->getContainer()->get(MediaFolderConfigIndexer::class);
        $this->context = Context::createDefaultContext();
    }

    public function testOnRefreshItUpdatesChildConfig(): void
    {
        $parentId = Uuid::randomHex();
        $child1Id = Uuid::randomHex();
        $child1_1Id = Uuid::randomHex();
        $configId = Uuid::randomHex();
        $newConfigId = Uuid::randomHex();

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

        $this->folderRepository->update([
            [
                'id' => $parentId,
                'configuration' => [
                    'id' => $newConfigId,
                    'createThumbnails' => false,
                ],
            ],
        ], $this->context);

        $children = $this->folderRepository->search(new Criteria([$child1Id, $child1_1Id]), $this->context);

        static::assertEquals($newConfigId, $children->get($child1Id)->getConfigurationId());
        static::assertEquals($newConfigId, $children->get($child1_1Id)->getConfigurationId());
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

        $this->conncetion->createQueryBuilder()
            ->update('media_folder')
            ->set('media_folder_configuration_id', ':configId')
            ->andWhere('id in (:ids)')
            ->setParameter('configId', Uuid::randomBytes())
            ->setParameter(
                'ids',
                [Uuid::fromHexToBytes($child1Id), Uuid::fromHexToBytes($child1_1Id)],
                Connection::PARAM_STR_ARRAY
            )
            ->execute();

        $this->configIndexer->index(new \DateTime());

        $children = $this->folderRepository->search(new Criteria([$child1Id, $child1_1Id]), $this->context);

        static::assertEquals($configId, $children->get($child1Id)->getConfigurationId());
        static::assertEquals($configId, $children->get($child1_1Id)->getConfigurationId());
    }
}
