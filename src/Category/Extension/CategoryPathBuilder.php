<?php

namespace Shopware\Category\Extension;

use Doctrine\DBAL\Connection;
use Shopware\Category\Repository\CategoryRepository;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermQuery;

class CategoryPathBuilder
{
    /**
     * @var CategoryRepository
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(CategoryRepository $repository, Connection $connection)
    {
        $this->repository = $repository;
        $this->connection = $connection;
    }

    public function update(string $parentUuid, TranslationContext $context): void
    {
        $parents = $this->loadParents($parentUuid, $context);
        $parent = $parents->get($parentUuid);
        $this->updateRecursive($parent, $parents, $context);
    }

    private function updateRecursive(
        CategoryBasicStruct $parent,
        CategoryBasicCollection $parents,
        TranslationContext $context
    ): void {
        $categories = $this->updateByParent($parent, $parents, $context);
        foreach ($categories as $category) {
            $nestedParents = clone $parents;
            $nestedParents->add($category);
            $this->updateRecursive($category, $nestedParents, $context);
        }
    }

    private function updateByParent(
        CategoryBasicStruct $parent,
        CategoryBasicCollection $parents,
        TranslationContext $context
    ): CategoryBasicCollection {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parent_uuid', $parent->getUuid()));
        $categories = $this->repository->search($criteria, $context);

        $pathUpdate = $this->connection->prepare('UPDATE category SET path = :path, level = :level WHERE uuid = :uuid');
        $nameUpdate = $this->connection->prepare('UPDATE category_translation SET path_names = :names WHERE category_uuid = :uuid');

        $updates = [];
        /** @var CategoryBasicStruct $category */
        foreach ($categories as $category) {
            $uuidPath = implode('|', $parents->getUuids());

            $names = $parents->map(
                function (CategoryBasicStruct $parent) {
                    if (0 === $parent->getLevel()) {
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

        $this->repository->update($updates, $context);

        return $categories;
    }

    private function loadParents(string $parentUuid, TranslationContext $context): CategoryBasicCollection
    {
        $parents = $this->repository->read([$parentUuid], $context);
        $parent = $parents->get($parentUuid);

        if (null !== $parent->getParentUuid()) {
            $parents = $parents->merge(
                $this->loadParents($parent->getParentUuid(), $context)
            );
        }

        return $parents;
    }
}
