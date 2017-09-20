<?php

namespace Shopware\Category\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Category\Factory\CategoryBasicFactory;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CategoryBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var CategoryBasicFactory
     */
    private $factory;

    public function __construct(
        CategoryBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): CategoryBasicCollection
    {
        if (empty($uuids)) {
            return new CategoryBasicCollection();
        }

        $categoriesCollection = $this->read($uuids, $context);

        return $categoriesCollection;
    }

    private function read(array $uuids, TranslationContext $context): CategoryBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('category.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CategoryBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CategoryBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
