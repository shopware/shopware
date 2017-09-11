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

namespace Shopware\AreaCountry\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class AreaCountryBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('area_country', 'areaCountry');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'areaCountry.uuid as _array_key_',
                'areaCountry.name as __areaCountry_name',
                'areaCountry.iso as __areaCountry_iso',
                'areaCountry.en as __areaCountry_en',
                'areaCountry.position as __areaCountry_position',
                'areaCountry.notice as __areaCountry_notice',
                'areaCountry.shipping_free as __areaCountry_shipping_free',
                'areaCountry.tax_free as __areaCountry_tax_free',
                'areaCountry.taxfree_for_vat_id as __areaCountry_taxfree_for_vat_id',
                'areaCountry.taxfree_vatid_checked as __areaCountry_taxfree_vatid_checked',
                'areaCountry.active as __areaCountry_active',
                'areaCountry.iso3 as __areaCountry_iso3',
                'areaCountry.display_state_in_registration as __areaCountry_display_state_in_registration',
                'areaCountry.force_state_in_registration as __areaCountry_force_state_in_registration',
                'areaCountry.uuid as __areaCountry_uuid',
                'areaCountry.area_uuid as __areaCountry_area_uuid',
            ]
        );

        //$query->leftJoin('areaCountry', 'areaCountry_translation', 'areaCountryTranslation', 'areaCountry.uuid = areaCountryTranslation.areaCountry_uuid AND areaCountryTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
