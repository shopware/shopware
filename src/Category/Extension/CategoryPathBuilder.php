<?php declare(strict_types=1);

namespace Shopware\Category\Extension;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Category\Event\Category\CategoryWrittenEvent;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Category\Struct\CategoryBasicStruct;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Context\Struct\TranslationContext;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CategoryPathBuilder implements EventSubscriberInterface
{
    public const ROOT = '57f4ecb1-1628-43e6-9dab-c4a6a7b66eab';

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
        $context = TranslationContext::createDefaultContext();

        $parentIds = $this->fetchParentIds($event->getIds());

        foreach ($parentIds as $id) {
            $this->update($id, $context);
        }
    }

    public function update(string $parentId, TranslationContext $context): void
    {
        $this->connection->executeUpdate('UPDATE category SET path = NULL');
        $count = (int) $this->connection->fetchColumn('SELECT COUNT(id) FROM category WHERE parent_id IS NOT NULL');

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

    private function updateRecursive(CategoryBasicStruct $parent, CategoryBasicCollection $parents, TranslationContext $context): void
    {
        $categories = $this->updateByParent($parent, $parents, $context);
        foreach ($categories as $category) {
            $nestedParents = clone $parents;
            $nestedParents->add($category);
            $this->updateRecursive($category, $nestedParents, $context);
        }
    }

    private function updateByParent(CategoryBasicStruct $parent, CategoryBasicCollection $parents, TranslationContext $context): CategoryBasicCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parentId', $parent->getId()));
        $categories = $this->repository->search($criteria, $context);

        $pathUpdate = $this->connection->prepare('UPDATE category SET path = :path, level = :level WHERE id = :id');
        $nameUpdate = $this->connection->prepare('UPDATE category_translation SET path_names = :names WHERE category_id = :id');

        /** @var CategoryBasicStruct $category */
        foreach ($categories as $category) {
            $idPath = implode('|', $parents->getIds());

            $names = $parents->map(
                function (CategoryBasicStruct $parent) {
                    if ($parent->getLevel() === 0) {
                        return null;
                    }

                    return $parent->getName();
                }
            );
            $names = implode('|', array_filter($names));

            $id = Uuid::fromString($category->getId())->getBytes();

            $pathUpdate->execute([
                'path' => '|' . $idPath . '|',
                'id' => $id,
                'level' => $parent->getLevel() + 1,
            ]);
            $nameUpdate->execute([
                'names' => '|' . $names . '|',
                'id' => $id,
            ]);
        }

        $this->eventDispatcher->dispatch(
            ProgressAdvancedEvent::NAME,
            new ProgressAdvancedEvent(count($categories))
        );

        return $categories;
    }

    private function loadParents(string $parentId, TranslationContext $context): CategoryBasicCollection
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

    private function fetchParentIds(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['parent_id']);
        $query->from('category');
        $query->where('category.id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $parents = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $parents = array_filter($parents);

        return array_map(function (string $id) {
            return Uuid::fromBytes($id)->toString();
        }, $parents);
    }
}
