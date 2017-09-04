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

namespace Shopware\Media\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\Media\Struct\MediaBasicStruct;

class MediaBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): MediaBasicStruct
    {
        $media = new MediaBasicStruct();

        $media->setId((int)$data['__media_id']);
        $media->setUuid((string)$data['__media_uuid']);
        $media->setAlbumUuid((string)$data['__media_album_uuid']);
        $media->setName((string)$data['__media_name']);
        $media->setDescription((string)$data['__media_description']);
        $media->setFileName((string)$data['__media_file_name']);
        $media->setMimeType((string)$data['__media_mime_type']);
        $media->setFileSize((int)$data['__media_file_size']);
        $media->setMetaData(isset($data['__media_meta_data']) ? (string)$data['__media_meta_data'] : null);
        $media->setCreatedAt(new \DateTime($data['__media_created_at']));
        $media->setUserUuid(isset($data['__media_user_uuid']) ? (string)$data['__media_user_uuid'] : null);
        $media->setAlbumId((int)$data['__media_album_id']);
        $media->setUserId((int)$data['__media_user_id']);
        $media->setUpdatedAt(isset($data['__media_updated_at']) ? new \DateTime($data['__media_updated_at']) : null);

        return $media;
    }
}
