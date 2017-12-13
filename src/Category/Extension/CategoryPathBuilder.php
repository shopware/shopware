<?php declare(strict_types=1);

namespace Shopware\Category\Extension;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Category\Collection\CategoryBasicCollection;
use Shopware\Category\Event\Category\CategoryWrittenEvent;
use Shopware\Category\Repository\CategoryRepository;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
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
        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);

        $parentUuids = $this->fetchParentIds($event->getUuids());

        foreach ($parentUuids as $uuid) {
            $this->update($uuid, $context);
        }
    }

    public function update(string $parentUuid, TranslationContext $context): void
    {
        $parents = $this->loadParents($parentUuid, $context);
        $parent = $parents->get($parentUuid);
        $this->updateRecursive($parent, $parents, $context);

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Category path build')
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
        $criteria->addFilter(new TermQuery('category.parentUuid', $parent->getUuid()));
        $categories = $this->repository->search($criteria, $context);

        $pathUpdate = $this->connection->prepare('UPDATE category SET path = :path, level = :level WHERE uuid = :uuid');
        $nameUpdate = $this->connection->prepare('UPDATE category_translation SET path_names = :names WHERE category_uuid = :uuid');

        /** @var CategoryBasicStruct $category */
        foreach ($categories as $category) {
            $uuidPath = implode('|', $parents->getUuids());

            $names = $parents->map(
                function (CategoryBasicStruct $parent) {
                    if ($parent->getLevel() === 0) {
                        return null;
                    }

                    return $parent->getName();
                }
            );
            $names = implode('|', array_filter($names));

            $pathUpdate->execute([
                'path' => '|' . $uuidPath . '|',
                'uuid' => $category->getUuid(),
                'level' => $parent->getLevel() + 1,
            ]);
            $nameUpdate->execute([
                'names' => '|' . $names . '|',
                'uuid' => $category->getUuid(),
            ]);
        }

        $this->eventDispatcher->dispatch(
            ProgressAdvancedEvent::NAME,
            new ProgressAdvancedEvent(count($categories))
        );

        return $categories;
    }

    private function loadParents(string $parentUuid, TranslationContext $context): CategoryBasicCollection
    {
        $parents = $this->repository->readBasic([$parentUuid], $context);
        $parent = $parents->get($parentUuid);

        if ($parent->getParentUuid() !== null) {
            $parents->merge(
                $this->loadParents($parent->getParentUuid(), $context)
            );
        }

        return $parents;
    }

    private function fetchParentIds(array $uuids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['parent_uuid']);
        $query->from('category');
        $query->where('category.uuid IN (:uuids)');
        $query->setParameter('uuids', $uuids, Connection::PARAM_STR_ARRAY);
        $parents = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        return array_filter($parents);
    }
}
