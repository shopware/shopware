<?php declare(strict_types=1);
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

namespace Shopware\Album\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class AlbumBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('album', 'album');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'album.uuid as _array_key_',
            'album.uuid as __album_uuid',
            'album.id as __album_id',
            'album.name as __album_name',
            'album.parent_uuid as __album_parent_uuid',
            'album.parent_id as __album_parent_id',
            'album.position as __album_position',
            'album.create_thumbnails as __album_create_thumbnails',
            'album.thumbnail_size as __album_thumbnail_size',
            'album.icon as __album_icon',
            'album.thumbnail_high_dpi as __album_thumbnail_high_dpi',
            'album.thumbnail_quality as __album_thumbnail_quality',
            'album.thumbnail_high_dpi_quality as __album_thumbnail_high_dpi_quality',
        ]);

        //$query->leftJoin('album', 'album_translation', 'albumTranslation', 'album.uuid = albumTranslation.album_uuid AND albumTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
