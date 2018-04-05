<?php declare(strict_types=1);

namespace Shopware\Category\Extension;

use Doctrine\DBAL\Connection;
use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Category\Event\Category\CategoryWrittenEvent;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Category\Struct\CategoryBasicStruct;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Context\Struct\ShopContext;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CategoryPathBuilder implements EventSubscriberInterface
{
    /**
     * @var CategoryRepository
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(CategoryRepository $repository, Connection $connection, EventDispatcherInterface $eventDispatcher)
    {
        $this->repository = $repository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents()
    {
        return [
            CategoryWrittenEvent::NAME => 'categoryWritten',
        ];
    }

    public function categoryWritten(CategoryWrittenEvent $event): void
    {
        $context = $event->getContext();

        $parentIds = $this->fetchParentIds($event->getIds(), $event->getContext());
        $parentIds = array_keys(array_flip($parentIds));

        foreach ($parentIds as $id) {
            $this->update($id, $context);
        }
    }

    public function update(string $parentId, ShopContext $context): void
    {
        $version = Uuid::fromStringToBytes($context->getVersionId());

        $count = (int) $this->connection->fetchColumn(
            'SELECT COUNT(id) FROM category WHERE parent_id IS NOT NULL AND version_id = :version',
            ['version' => $version]
        );

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start building category inheritance', $count)
        );

        $parents = $this->loadParents($parentId, $context);
        $parent = $parents->get($parentId);
        $this->updateRecursive($parent, $parents, $context);

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished building category inheritance')
        );
    }

    private function updateRecursive(CategoryBasicStruct $parent, CategoryBasicCollection $parents, ShopContext $context): void
    {
        $categories = $this->updateByParent($parent, $parents, $context);
        foreach ($categories as $category) {
            $nestedParents = clone $parents;
            $nestedParents->add($category);
            $this->updateRecursive($category, $nestedParents, $context);
        }
    }

    private function updateByParent(CategoryBasicStruct $parent, CategoryBasicCollection $parents, ShopContext $context): CategoryBasicCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parentId', $parent->getId()));
        $categories = $this->repository->search($criteria, $context);

        $pathUpdate = $this->connection->prepare('UPDATE category SET path = :path, level = :level WHERE id = :id AND version_id = :version');
        $nameUpdate = $this->connection->prepare('UPDATE category_translation SET path_names = :names WHERE category_id = :id AND version_id = :version');

        $version = Uuid::fromStringToBytes($context->getVersionId());

        /** @var CategoryBasicStruct $category */
        foreach ($categories as $category) {
            $idPath = implode('|', $parents->getIds());

            $names = $parents->map(
                function (CategoryBasicStruct $parent) {
                    if ($parent->getLevel() === 0) {
                        return null;
                    }
                    if ($parent->getParentId() === Defaults::ROOT_CATEGORY) {
                        return null;
                    }

                    return $parent->getName();
                }
            );
            $names = implode('|', array_filter($names));

            $id = Uuid::fromStringToBytes($category->getId());

            $pathUpdate->execute([
                'path' => '|' . $idPath . '|',
                'id' => $id,
                'level' => $parent->getLevel() + 1,
                'version' => $version,
            ]);
            $nameUpdate->execute([
                'names' => '|' . $names . '|',
                'id' => $id,
                'version' => $version,
            ]);
        }

        $this->eventDispatcher->dispatch(
            ProgressAdvancedEvent::NAME,
            new ProgressAdvancedEvent(count($categories))
        );

        return $categories;
    }

    private function loadParents(string $parentId, ShopContext $context): CategoryBasicCollection
    {
        $parents = $this->repository->readBasic([$parentId], $context);
        $parent = $parents->get($parentId);

        if ($parent->getParentId() !== null) {
            $parents->merge(
                $this->loadParents($parent->getParentId(), $context)
            );
        }

        return $parents;
    }

    private function fetchParentIds(array $ids, ShopContext $context): array
    {
        $ids = array_map(function($id) {
            return Uuid::fromStringToBytes($id);
        }, $ids);

        $query = $this->connection->createQueryBuilder();
        $query->select(['parent_id']);
        $query->from('category');
        $query->andWhere('category.id IN (:ids)');
        $query->andWhere('category.version_id = :version');

        $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $parents = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $parents = array_filter($parents);

        return array_map(function (string $id) {
            return Uuid::fromBytesToHex($id);
        }, $parents);
    }
}
