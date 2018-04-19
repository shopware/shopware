<?php
declare(strict_types=1);
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

namespace Shopware\PaymentMethod\Struct;

use Shopware\Framework\Struct\AttributeHydrator;
use Shopware\Framework\Struct\Hydrator;
use Shopware\Serializer\JsonSerializer;

class PaymentMethodHydrator extends Hydrator
{
    /**
     * @var AttributeHydrator
     */
    private $attributeHydrator;

    /**
     * @var JsonSerializer
     */
    private $serializer;

    public function __construct(AttributeHydrator $attributeHydrator, JsonSerializer $serializer)
    {
        $this->attributeHydrator = $attributeHydrator;
        $this->serializer = $serializer;
    }

    public function hydrate(array $data): PaymentMethod
    {
        $id = (int) $data['__paymentMethod_id'];
        $translation = $this->getTranslation($data, '__paymentMethod', [], $id);
        $data = array_merge($data, $translation);

        $paymentMethod = new PaymentMethod(
            (int) $data['__paymentMethod_id'],
            (string) $data['__paymentMethod_name'],
            (string) $data['__paymentMethod_description'],
            (string) $data['__paymentMethod_class']
        );

        $paymentMethod->setDescription((string) $data['__paymentMethod_additionaldescription']);
        $paymentMethod->setTable((string) $data['__paymentMethod_table']);
        $paymentMethod->setTemplate((string) $data['__paymentMethod_template']);
        $paymentMethod->setHidden((bool) $data['__paymentMethod_hide']);
        $paymentMethod->setPosition((int) $data['__paymentMethod_position']);
        $paymentMethod->setActive((bool) $data['__paymentMethod_active']);
        $paymentMethod->setEsdActive((bool) $data['__paymentMethod_esdactive']);
        $paymentMethod->setIFrameUrl((string) $data['__paymentMethod_embediframe']);
        $paymentMethod->setAction($data['__paymentMethod_action']);
        $paymentMethod->setMobileInactive((bool) $data['__paymentMethod_mobile_inactive']);

        if (isset($data['__paymentMethod_debit_percent']) && $data['__paymentMethod_debit_percent'] != 0) {
            $paymentMethod->setPercentageSurcharge((float) $data['__paymentMethod_debit_percent']);
        }
        if (isset($data['__paymentMethod_surcharge']) && $data['__paymentMethod_surcharge']) {
            $paymentMethod->setSurcharge((float) $data['__paymentMethod_surcharge']);
        }

        if ($data['__paymentMethod_rules']) {
            $rule = json_decode($data['__paymentMethod_rules'], true);
            $paymentMethod->setRule(
                $this->serializer->deserialize($rule)
            );
        }

        if ($data['__paymentMethod_pluginId']) {
            $paymentMethod->setPluginId((int) $data['__paymentMethod_pluginId']);
        }
        if ($data['__paymentMethod_source']) {
            $paymentMethod->setSource((int) $data['__paymentMethod_source']);
        }
        if (!empty($data['__paymentMethodAttribute_id'])) {
            $this->attributeHydrator->addAttribute($paymentMethod, $data, 'paymentMethodAttribute');
        }

        return $paymentMethod;
    }
}
