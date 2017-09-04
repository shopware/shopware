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

namespace Shopware\Customer\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Reader\Query\CustomerGroupBasicQuery;

class CustomerBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('customer', 'customer');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'customer.uuid as _array_key_',
                'customer.id as __customer_id',
                'customer.uuid as __customer_uuid',
                'customer.password as __customer_password',
                'customer.encoder as __customer_encoder',
                'customer.email as __customer_email',
                'customer.active as __customer_active',
                'customer.account_mode as __customer_account_mode',
                'customer.confirmation_key as __customer_confirmation_key',
                'customer.last_payment_method_id as __customer_last_payment_method_id',
                'customer.last_payment_method_uuid as __customer_last_payment_method_uuid',
                'customer.first_login as __customer_first_login',
                'customer.last_login as __customer_last_login',
                'customer.session_id as __customer_session_id',
                'customer.newsletter as __customer_newsletter',
                'customer.validation as __customer_validation',
                'customer.affiliate as __customer_affiliate',
                'customer.customer_group_key as __customer_customer_group_key',
                'customer.customer_group_uuid as __customer_customer_group_uuid',
                'customer.default_payment_method_id as __customer_default_payment_method_id',
                'customer.default_payment_method_uuid as __customer_default_payment_method_uuid',
                'customer.shop_id as __customer_shop_id',
                'customer.shop_uuid as __customer_shop_uuid',
                'customer.main_shop_id as __customer_main_shop_id',
                'customer.main_shop_uuid as __customer_main_shop_uuid',
                'customer.referer as __customer_referer',
                'customer.price_group_id as __customer_price_group_id',
                'customer.price_group_uuid as __customer_price_group_uuid',
                'customer.internal_comment as __customer_internal_comment',
                'customer.failed_logins as __customer_failed_logins',
                'customer.locked_until as __customer_locked_until',
                'customer.default_billing_address_id as __customer_default_billing_address_id',
                'customer.default_billing_address_uuid as __customer_default_billing_address_uuid',
                'customer.default_shipping_address_id as __customer_default_shipping_address_id',
                'customer.default_shipping_address_uuid as __customer_default_shipping_address_uuid',
                'customer.title as __customer_title',
                'customer.salutation as __customer_salutation',
                'customer.first_name as __customer_first_name',
                'customer.last_name as __customer_last_name',
                'customer.birthday as __customer_birthday',
                'customer.customer_number as __customer_customer_number',
            ]
        );

        //$query->leftJoin('customer', 'customer_translation', 'customerTranslation', 'customer.uuid = customerTranslation.customer_uuid AND customerTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());

        $query->leftJoin(
            'customer',
            'customer_group',
            'customerGroup',
            'customerGroup.uuid = customer.customer_group_uuid'
        );
        CustomerGroupBasicQuery::addRequirements($query, $context);
    }
}
