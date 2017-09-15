<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

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
