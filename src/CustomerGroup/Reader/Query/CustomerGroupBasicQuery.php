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

namespace Shopware\CustomerGroup\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class CustomerGroupBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('customer_group', 'customerGroup');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'customerGroup.uuid as _array_key_',
            'customerGroup.uuid as __customerGroup_uuid',
            'customerGroup.group_key as __customerGroup_group_key',
            'customerGroup.description as __customerGroup_description',
            'customerGroup.display_gross_prices as __customerGroup_display_gross_prices',
            'customerGroup.input_gross_prices as __customerGroup_input_gross_prices',
            'customerGroup.mode as __customerGroup_mode',
            'customerGroup.discount as __customerGroup_discount',
            'customerGroup.minimum_order_amount as __customerGroup_minimum_order_amount',
            'customerGroup.minimum_order_amount_surcharge as __customerGroup_minimum_order_amount_surcharge',
        ]);

        //$query->leftJoin('customerGroup', 'customerGroup_translation', 'customerGroupTranslation', 'customerGroup.uuid = customerGroupTranslation.customerGroup_uuid AND customerGroupTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
