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

namespace Shopware\Area\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class AreaBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('area', 'area');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'area.uuid as _array_key_',
            'area.id as __area_id',
            'area.uuid as __area_uuid',
            'area.name as __area_name',
            'area.active as __area_active',
        ]);

        //$query->leftJoin('area', 'area_translation', 'areaTranslation', 'area.uuid = areaTranslation.area_uuid AND areaTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
