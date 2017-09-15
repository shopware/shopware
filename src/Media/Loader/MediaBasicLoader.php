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

namespace Shopware\Media\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Media\Factory\MediaBasicFactory;
use Shopware\Media\Struct\MediaBasicCollection;
use Shopware\Media\Struct\MediaBasicStruct;

class MediaBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var MediaBasicFactory
     */
    private $factory;

    public function __construct(
        MediaBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): MediaBasicCollection
    {
        $medias = $this->read($uuids, $context);

        return $medias;
    }

    private function read(array $uuids, TranslationContext $context): MediaBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('media.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new MediaBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new MediaBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
