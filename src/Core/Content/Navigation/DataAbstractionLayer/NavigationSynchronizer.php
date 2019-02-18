<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NavigationSynchronizer implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $navigationRepository;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(EntityRepositoryInterface $navigationRepository, Connection $connection)
    {
        $this->navigationRepository = $navigationRepository;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            EntityWrittenContainerEvent::NAME => ['refresh', 400],
        ];
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $this->deleteNavigations($event);

        $this->updateExistingNavigations($event);

        $this->insertNewNavigations($event);
    }

    private function deleteNavigations(EntityWrittenContainerEvent $event): void
    {
        $categoryEvent = $event->getEventByDefinition(CategoryDefinition::class);

        if (!$categoryEvent instanceof EntityDeletedEvent) {
            return;
        }

        $mapped = array_map(function ($id) {
            return ['id' => $id];
        }, $categoryEvent->getIds());

        $this->navigationRepository->delete($mapped, $event->getContext());
    }

    private function updateExistingNavigations(EntityWrittenContainerEvent $event): void
    {
        $categoryEvent = $event->getEventByDefinition(CategoryDefinition::class);

        if (!$categoryEvent || $categoryEvent instanceof EntityDeletedEvent) {
            return;
        }

        $categoryIds = [];
        foreach ($categoryEvent->getWriteResults() as $result) {
            if (!$result->getExistence() || !$result->getExistence()->exists()) {
                continue;
            }
            $categoryIds[] = $result->getPrimaryKey();
        }

        if (empty($categoryIds)) {
            return;
        }

        $context = $event->getContext();

        //first fetch all data for categories
        $categories = $this->getUpdatedCategories($categoryIds);

        //now get all navigations which are linked for the updated categories
        $navigations = $this->getNavigationsForUpdate($categoryIds);

        $updates = [];

        //prepare a query to detect the new navigation parent for the parent of the updated category
        $query = $this->connection->createQueryBuilder();
        $query->select('navigation.id');
        $query->from('navigation');
        $query->andWhere('navigation.category_id = :id');
        $query->andWhere('(navigation.path LIKE :path OR navigation.category_id = :root)');

        foreach ($navigations as $navigation) {
            $id = Uuid::fromBytesToHex($navigation['id']);

            $rootId = array_filter(explode('|', (string) $navigation['path']));
            $rootId = array_shift($rootId);

            if (!$rootId) {
                continue;
            }

            $categoryId = Uuid::fromBytesToHex($navigation['category_id']);
            $categoryParentId = $categories[$categoryId];

            $query->setParameter('id', Uuid::fromHexToBytes($categoryParentId));
            $query->setParameter('path', '%|' . $rootId . '|%');
            $query->setParameter('root', Uuid::fromHexToBytes($rootId));

            $navigationParent = $query->execute()->fetchColumn();
            $navigationParent = $navigationParent ? Uuid::fromBytesToHex((string) $navigationParent) : null;

            $updates[] = ['id' => $id, 'parentId' => $navigationParent];
        }

        if (empty($updates)) {
            return;
        }

        $this->navigationRepository->update($updates, $context);
    }

    private function insertNewNavigations(EntityWrittenContainerEvent $container): void
    {
        $event = $container->getEventByDefinition(CategoryDefinition::class);
        if (!$event) {
            return;
        }

        $mapping = [];

        $parentIdBytes = [];
        foreach ($event->getWriteResults() as $result) {
            if (!$result->getExistence() || $result->getExistence()->exists()) {
                continue;
            }

            $payload = $result->getPayload();
            if (!isset($payload['parentId'])) {
                continue;
            }
            $parentId = $payload['parentId'];

            $mapping[$parentId][] = [
                'id' => $result->getPrimaryKey(),
            ];

            $parentIdBytes[] = Uuid::fromHexToBytes($parentId);
        }

        $rawNavigations = $this->connection->fetchAll(
            'SELECT id, category_id FROM navigation WHERE category_id IN (:ids)',
            ['ids' => $parentIdBytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $navigations = [];
        foreach ($rawNavigations as $navigation) {
            $parentId = Uuid::fromBytesToHex($navigation['id']);
            $categoryId = Uuid::fromBytesToHex($navigation['category_id']);

            $navigations[$categoryId][] = $parentId;
        }

        if (empty($navigations)) {
            return;
        }

        $inserts = [];
        foreach ($mapping as $categoryId => $children) {
            $parents = $navigations[$categoryId];

            foreach ($parents as $parentId) {
                foreach ($children as $child) {
                    $id = Uuid::uuid4()->getHex();

                    $inserts[] = [
                        'id' => $id,
                        'parentId' => $parentId,
                        'categoryId' => $child['id'],
                        'name' => 'test',
                    ];

                    $navigations[$child['id']][] = $id;
                }
            }
        }

        if (empty($inserts)) {
            return;
        }

        $this->navigationRepository->create($inserts, $event->getContext());
    }

    private function getNavigationsForUpdate(array $categoryIds): array
    {
        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $categoryIds);

        return $this->connection->fetchAll(
            'SELECT id, category_id, `path` FROM navigation WHERE id IN (:ids)',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function getUpdatedCategories(array $categoryIds): array
    {
        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $categoryIds);

        $categories = $this->connection->fetchAll(
            'SELECT id, parent_id FROM category WHERE id IN (:ids)',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $mapped = [];
        foreach ($categories as $category) {
            $id = Uuid::fromBytesToHex($category['id']);
            $parentId = $category['parent_id'] ? Uuid::fromBytesToHex($category['parent_id']) : null;

            $mapped[$id] = $parentId;
        }

        return $mapped;
    }
}
