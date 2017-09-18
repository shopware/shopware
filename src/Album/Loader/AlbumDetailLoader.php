<?php

namespace Shopware\Album\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Album\Factory\AlbumDetailFactory;
use Shopware\Album\Struct\AlbumDetailCollection;
use Shopware\Album\Struct\AlbumDetailStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Media\Searcher\MediaSearcher;
use Shopware\Media\Searcher\MediaSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class AlbumDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var AlbumDetailFactory
     */
    private $factory;

    /**
     * @var MediaSearcher
     */
    private $mediaSearcher;

    public function __construct(
        AlbumDetailFactory $factory,
MediaSearcher $mediaSearcher
    ) {
        $this->factory = $factory;
        $this->mediaSearcher = $mediaSearcher;
    }

    public function load(array $uuids, TranslationContext $context): AlbumDetailCollection
    {
        if (empty($uuids)) {
            return new AlbumDetailCollection();
        }

        $albums = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('media.album_uuid', $uuids));
        /** @var MediaSearchResult $medias */
        $medias = $this->mediaSearcher->search($criteria, $context);

        /** @var AlbumDetailStruct $album */
        foreach ($albums as $album) {
            $album->setMedias($medias->filterByAlbumUuid($album->getUuid()));
        }

        return $albums;
    }

    private function read(array $uuids, TranslationContext $context): AlbumDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('album.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new AlbumDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new AlbumDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
