<?php

namespace Shopware\Album\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Album\Factory\AlbumBasicFactory;
use Shopware\Album\Struct\AlbumBasicCollection;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class AlbumBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var AlbumBasicFactory
     */
    private $factory;

    public function __construct(
        AlbumBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): AlbumBasicCollection
    {
        if (empty($uuids)) {
            return new AlbumBasicCollection();
        }

        $albums = $this->read($uuids, $context);

        return $albums;
    }

    private function read(array $uuids, TranslationContext $context): AlbumBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('album.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new AlbumBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new AlbumBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
