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

namespace Shopware\Album\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Album\Struct\AlbumDetailStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Media\Factory\MediaBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class AlbumDetailFactory extends AlbumBasicFactory
{
    /**
     * @var MediaBasicFactory
     */
    protected $mediaFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        MediaBasicFactory $mediaFactory
    ) {
        parent::__construct($connection, $extensions);
        $this->mediaFactory = $mediaFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        AlbumBasicStruct $album,
        QuerySelection $selection,
        TranslationContext $context
    ): AlbumBasicStruct {
        /** @var AlbumDetailStruct $album */
        $album = parent::hydrate($data, $album, $selection, $context);

        return $album;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($medias = $selection->filter('medias')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'media',
                $medias->getRootEscaped(),
                sprintf('%s.uuid = %s.album_uuid', $selection->getRootEscaped(), $medias->getRootEscaped())
            );

            $this->mediaFactory->joinDependencies($medias, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['medias'] = $this->mediaFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->extensions as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
