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

namespace Shopware\Album\Reader;

use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Framework\Struct\Hydrator;

class AlbumBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): AlbumBasicStruct
    {
        $album = new AlbumBasicStruct();

        $album->setUuid((string)$data['__album_uuid']);
        $album->setId((int)$data['__album_id']);
        $album->setName((string)$data['__album_name']);
        $album->setParentUuid(isset($data['__album_parent_uuid']) ? (string)$data['__album_parent_uuid'] : null);
        $album->setParentId(isset($data['__album_parent_id']) ? (int)$data['__album_parent_id'] : null);
        $album->setPosition((int)$data['__album_position']);
        $album->setCreateThumbnails((int)$data['__album_create_thumbnails']);
        $album->setThumbnailSize((string)$data['__album_thumbnail_size']);
        $album->setIcon((string)$data['__album_icon']);
        $album->setThumbnailHighDpi((bool)$data['__album_thumbnail_high_dpi']);
        $album->setThumbnailQuality(
            isset($data['__album_thumbnail_quality']) ? (int)$data['__album_thumbnail_quality'] : null
        );
        $album->setThumbnailHighDpiQuality(
            isset($data['__album_thumbnail_high_dpi_quality']) ? (int)$data['__album_thumbnail_high_dpi_quality'] : null
        );

        return $album;
    }
}
