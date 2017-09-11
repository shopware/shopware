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

namespace Shopware\PaymentMethod\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;

class PaymentMethodBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): PaymentMethodBasicStruct
    {
        $paymentMethod = new PaymentMethodBasicStruct();

        $paymentMethod->setUuid((string)$data['__paymentMethod_uuid']);
        $paymentMethod->setName((string)$data['__paymentMethod_name']);
        $paymentMethod->setDescription((string)$data['__paymentMethod_description']);
        $paymentMethod->setTemplate((string)$data['__paymentMethod_template']);
        $paymentMethod->setClass((string)$data['__paymentMethod_class']);
        $paymentMethod->setTable((string)$data['__paymentMethod_table']);
        $paymentMethod->setHide((bool)$data['__paymentMethod_hide']);
        $paymentMethod->setAdditionalDescription((string)$data['__paymentMethod_additional_description']);
        $paymentMethod->setDebitPercent((float)$data['__paymentMethod_debit_percent']);
        $paymentMethod->setSurcharge((float)$data['__paymentMethod_surcharge']);
        $paymentMethod->setSurchargeString((string)$data['__paymentMethod_surcharge_string']);
        $paymentMethod->setPosition((int)$data['__paymentMethod_position']);
        $paymentMethod->setActive((bool)$data['__paymentMethod_active']);
        $paymentMethod->setAllowEsd((bool)$data['__paymentMethod_allow_esd']);
        $paymentMethod->setUsedIframe((string)$data['__paymentMethod_used_iframe']);
        $paymentMethod->setHideProspect((bool)$data['__paymentMethod_hide_prospect']);
        $paymentMethod->setAction(
            isset($data['__paymentMethod_action']) ? (string)$data['__paymentMethod_action'] : null
        );
        $paymentMethod->setPluginId(
            isset($data['__paymentMethod_plugin_id']) ? (int)$data['__paymentMethod_plugin_id'] : null
        );
        $paymentMethod->setPluginUuid(
            isset($data['__paymentMethod_plugin_uuid']) ? (string)$data['__paymentMethod_plugin_uuid'] : null
        );
        $paymentMethod->setSource(isset($data['__paymentMethod_source']) ? (int)$data['__paymentMethod_source'] : null);
        $paymentMethod->setMobileInactive((bool)$data['__paymentMethod_mobile_inactive']);
        $paymentMethod->setRiskRules(
            isset($data['__paymentMethod_risk_rules']) ? (string)$data['__paymentMethod_risk_rules'] : null
        );

        return $paymentMethod;
    }
}
