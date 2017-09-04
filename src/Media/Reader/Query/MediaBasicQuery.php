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

namespace Shopware\Media\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class MediaBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('media', 'media');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'media.uuid as _array_key_',
            'media.uuid as __media_uuid',
            'media.album_uuid as __media_album_uuid',
            'media.name as __media_name',
            'media.description as __media_description',
            'media.file_name as __media_file_name',
            'media.mime_type as __media_mime_type',
            'media.file_size as __media_file_size',
            'media.meta_data as __media_meta_data',
            'media.created_at as __media_created_at',
            'media.user_uuid as __media_user_uuid',
            'media.id as __media_id',
            'media.album_id as __media_album_id',
            'media.user_id as __media_user_id',
            'media.updated_at as __media_updated_at',
        ]);

        //$query->leftJoin('media', 'media_translation', 'mediaTranslation', 'media.uuid = mediaTranslation.media_uuid AND mediaTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
