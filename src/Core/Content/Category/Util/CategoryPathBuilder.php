<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Util;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Event\EntityWrittenEvent;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CategoryPathBuilder implements EventSubscriberInterface
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(RepositoryInterface $repository, Connection $connection)
    {
        $this->repository = $repository;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            'category.written' => 'categoryWritten',
        ];
    }

    public function categoryWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();

        $parentIds = $this->fetchParentIds($event->getIds(), $event->getContext());
        $parentIds = array_keys(array_flip($parentIds));

        foreach ($parentIds as $id) {
            $this->update($id, $context);
        }
    }

    public function update(string $parentId, Context $context): void
    {
        $parents = $this->loadParents($parentId, $context);
        $parent = $parents->get($parentId);
        $this->updateRecursive($parent, $parents, $context);
    }

    private function updateRecursive(CategoryStruct $parent, CategoryCollection $parents, Context $context): void
    {
        $categories = $this->updateByParent($parent, $parents, $context);
        foreach ($categories as $category) {
            $nestedParents = clone $parents;
            $nestedParents->add($category);
            $this->updateRecursive($category, $nestedParents, $context);
        }
    }

    private function updateByParent(CategoryStruct $parent, CategoryCollection $parents, Context $context): CategoryCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parentId', $parent->getId()));
        $categories = $this->repository->search($criteria, $context);

        $pathUpdate = $this->connection->prepare('UPDATE category SET path = :path, level = :level WHERE id = :id AND version_id = :version AND tenant_id = :tenant');
        $nameUpdate = $this->connection->prepare('UPDATE category_translation SET path_names = :names WHERE category_id = :id AND category_version_id = :version AND category_tenant_id = :tenant');

        $version = Uuid::fromStringToBytes($context->getVersionId());
        $tenantId = Uuid::fromHexToBytes($context->getTenantId());

        /** @var CategoryStruct $category */
        foreach ($categories as $category) {
            $idPath = implode('|', $parents->getIds());

            $names = $parents->map(
                function (CategoryStruct $parent) {
                    if ($parent->getLevel() === 0) {
                        return null;
                    }
                    if ($parent->getParentId() === null) {
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
                'tenant' => $tenantId,
            ]);
            $nameUpdate->execute([
                'names' => '|' . $names . '|',
                'id' => $id,
                'version' => $version,
                'tenant' => $tenantId,
            ]);
        }

        /** @var CategoryCollection $entities */
        $entities = $categories->getEntities();

        return $entities;
    }

    private function loadParents(string $parentId, Context $context): CategoryCollection
    {
        /** @var CategoryCollection $parents */
        $parents = $this->repository->read(new ReadCriteria([$parentId]), $context);
        $parent = $parents->get($parentId);

        if ($parent->getParentId() !== null) {
            $parents->merge(
                $this->loadParents($parent->getParentId(), $context)
            );
        }

        return $parents;
    }

    private function fetchParentIds(array $ids, Context $context): array
    {
        $ids = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $ids);

        $query = $this->connection->createQueryBuilder();
        $query->select(['parent_id']);
        $query->from('category');
        $query->andWhere('category.id IN (:ids)');
        $query->andWhere('category.tenant_id = :tenant');
        $query->andWhere('category.version_id = :version');

        $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));
        $query->setParameter('tenant', Uuid::fromStringToBytes($context->getTenantId()));
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $parents = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $parents = array_filter($parents);

        return array_map(function (string $id) {
            return Uuid::fromBytesToHex($id);
        }, $parents);
    }
}
