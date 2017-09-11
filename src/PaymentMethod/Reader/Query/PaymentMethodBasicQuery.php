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

namespace Shopware\PaymentMethod\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class PaymentMethodBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('payment_method', 'paymentMethod');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'paymentMethod.uuid as _array_key_',
                'paymentMethod.uuid as __paymentMethod_uuid',
                'paymentMethod.name as __paymentMethod_name',
                'paymentMethod.description as __paymentMethod_description',
                'paymentMethod.template as __paymentMethod_template',
                'paymentMethod.class as __paymentMethod_class',
                'paymentMethod.table as __paymentMethod_table',
                'paymentMethod.hide as __paymentMethod_hide',
                'paymentMethod.additional_description as __paymentMethod_additional_description',
                'paymentMethod.debit_percent as __paymentMethod_debit_percent',
                'paymentMethod.surcharge as __paymentMethod_surcharge',
                'paymentMethod.surcharge_string as __paymentMethod_surcharge_string',
                'paymentMethod.position as __paymentMethod_position',
                'paymentMethod.active as __paymentMethod_active',
                'paymentMethod.allow_esd as __paymentMethod_allow_esd',
                'paymentMethod.used_iframe as __paymentMethod_used_iframe',
                'paymentMethod.hide_prospect as __paymentMethod_hide_prospect',
                'paymentMethod.action as __paymentMethod_action',
                'paymentMethod.plugin_uuid as __paymentMethod_plugin_uuid',
                'paymentMethod.source as __paymentMethod_source',
                'paymentMethod.mobile_inactive as __paymentMethod_mobile_inactive',
                'paymentMethod.risk_rules as __paymentMethod_risk_rules',
            ]
        );

        //$query->leftJoin('paymentMethod', 'paymentMethod_translation', 'paymentMethodTranslation', 'paymentMethod.uuid = paymentMethodTranslation.paymentMethod_uuid AND paymentMethodTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
