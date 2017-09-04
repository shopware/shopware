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

namespace Shopware\CustomerAddress\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\AreaCountry\Reader\Query\AreaCountryBasicQuery;
use Shopware\AreaCountryState\Reader\Query\AreaCountryStateBasicQuery;
use Shopware\Context\Struct\TranslationContext;

class CustomerAddressBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('customer_address', 'customerAddress');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'customerAddress.uuid as _array_key_',
                'customerAddress.id as __customerAddress_id',
                'customerAddress.uuid as __customerAddress_uuid',
                'customerAddress.customer_id as __customerAddress_customer_id',
                'customerAddress.customer_uuid as __customerAddress_customer_uuid',
                'customerAddress.company as __customerAddress_company',
                'customerAddress.department as __customerAddress_department',
                'customerAddress.salutation as __customerAddress_salutation',
                'customerAddress.title as __customerAddress_title',
                'customerAddress.first_name as __customerAddress_first_name',
                'customerAddress.last_name as __customerAddress_last_name',
                'customerAddress.street as __customerAddress_street',
                'customerAddress.zipcode as __customerAddress_zipcode',
                'customerAddress.city as __customerAddress_city',
                'customerAddress.area_country_id as __customerAddress_area_country_id',
                'customerAddress.area_country_uuid as __customerAddress_area_country_uuid',
                'customerAddress.area_country_state_id as __customerAddress_area_country_state_id',
                'customerAddress.area_country_state_uuid as __customerAddress_area_country_state_uuid',
                'customerAddress.vat_id as __customerAddress_vat_id',
                'customerAddress.phone_number as __customerAddress_phone_number',
                'customerAddress.additional_address_line1 as __customerAddress_additional_address_line1',
                'customerAddress.additional_address_line2 as __customerAddress_additional_address_line2',
            ]
        );

        //$query->leftJoin('customerAddress', 'customerAddress_translation', 'customerAddressTranslation', 'customerAddress.uuid = customerAddressTranslation.customerAddress_uuid AND customerAddressTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());

        $query->leftJoin(
            'customerAddress',
            'area_country',
            'areaCountry',
            'areaCountry.uuid = customerAddress.area_country_uuid'
        );
        AreaCountryBasicQuery::addRequirements($query, $context);

        $query->leftJoin(
            'customerAddress',
            'area_country_state',
            'areaCountryState',
            'areaCountryState.uuid = customerAddress.area_country_state_uuid'
        );
        AreaCountryStateBasicQuery::addRequirements($query, $context);
    }
}
