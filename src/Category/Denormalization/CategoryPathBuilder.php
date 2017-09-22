<?php

namespace Shopware\Category\Denormalization;

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

    public function __construct(CategoryRepository $repository)
    {
        $this->repository = $repository;
    }

    public function update(string $parentUuid, TranslationContext $context): void
    {
        $parents = $this->loadParents($parentUuid, $context);
        $parent = $parents->get($parentUuid);
        $this->updateRecursive($parent, $parents, $context);
    }

    private function updateByParent(
        CategoryBasicStruct $parent,
        CategoryBasicCollection $parents,
        TranslationContext $context
    ): CategoryBasicCollection {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parent_uuid', $parent->getUuid()));
        $categories = $this->repository->search($criteria, $context);

        $updates = [];
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

            $updates[] = [
                'uuid' => $category->getUuid(),
                'level' => $parent->getLevel() + 1,
                'path' => '|' . $uuidPath . '|',
                'pathNames' => '|' . $names . '|',
            ];
        }

        $this->repository->update($updates, $context);

        return $categories;
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

    private function loadParents(string $parentUuid, TranslationContext $context): CategoryBasicCollection
    {
        $parents = $this->repository->read([$parentUuid], $context);
        $parent = $parents->get($parentUuid);

        if ($parent->getParentUuid() !== null) {
            $parents = $parents->merge(
                $this->loadParents($parent->getParentUuid(), $context)
            );
        }

        return $parents;
    }
}
