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

namespace Shopware\AreaCountryState\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class AreaCountryStateBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('area_country_state', 'areaCountryState');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'areaCountryState.uuid as _array_key_',
                'areaCountryState.id as __areaCountryState_id',
                'areaCountryState.uuid as __areaCountryState_uuid',
                'areaCountryState.area_country_id as __areaCountryState_area_country_id',
                'areaCountryState.area_country_uuid as __areaCountryState_area_country_uuid',
                'areaCountryState.name as __areaCountryState_name',
                'areaCountryState.short_code as __areaCountryState_short_code',
                'areaCountryState.position as __areaCountryState_position',
                'areaCountryState.active as __areaCountryState_active',
            ]
        );

        //$query->leftJoin('areaCountryState', 'areaCountryState_translation', 'areaCountryStateTranslation', 'areaCountryState.uuid = areaCountryStateTranslation.areaCountryState_uuid AND areaCountryStateTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
