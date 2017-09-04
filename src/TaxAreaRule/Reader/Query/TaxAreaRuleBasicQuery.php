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

namespace Shopware\TaxAreaRule\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class TaxAreaRuleBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('tax_area_rule', 'taxAreaRule');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'taxAreaRule.uuid as _array_key_',
                'taxAreaRule.id as __taxAreaRule_id',
                'taxAreaRule.uuid as __taxAreaRule_uuid',
                'taxAreaRule.area_id as __taxAreaRule_area_id',
                'taxAreaRule.area_uuid as __taxAreaRule_area_uuid',
                'taxAreaRule.area_country_id as __taxAreaRule_area_country_id',
                'taxAreaRule.area_country_uuid as __taxAreaRule_area_country_uuid',
                'taxAreaRule.area_country_state_id as __taxAreaRule_area_country_state_id',
                'taxAreaRule.area_country_state_uuid as __taxAreaRule_area_country_state_uuid',
                'taxAreaRule.tax_id as __taxAreaRule_tax_id',
                'taxAreaRule.tax_uuid as __taxAreaRule_tax_uuid',
                'taxAreaRule.customer_group_id as __taxAreaRule_customer_group_id',
                'taxAreaRule.customer_group_uuid as __taxAreaRule_customer_group_uuid',
                'taxAreaRule.tax_rate as __taxAreaRule_tax_rate',
                'taxAreaRule.name as __taxAreaRule_name',
                'taxAreaRule.active as __taxAreaRule_active',
            ]
        );

        //$query->leftJoin('taxAreaRule', 'taxAreaRule_translation', 'taxAreaRuleTranslation', 'taxAreaRule.uuid = taxAreaRuleTranslation.taxAreaRule_uuid AND taxAreaRuleTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
