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

namespace Shopware\ShippingMethod\Struct;

use Shopware\Framework\Struct\AttributeHydrator;
use Shopware\Framework\Struct\Hydrator;

class ShippingMethodHydrator extends Hydrator
{
    /**
     * @var AttributeHydrator
     */
    private $attributeHydrator;

    /**
     * @var array
     */
    private $mapping = [
        'dispatch_status_link' => 'status_link',
        'dispatch_description' => 'description',
        'dispatch_name' => 'name',
    ];

    /**
     * @param \Shopware\Framework\Struct\AttributeHydrator $attributeHydrator
     */
    public function __construct(AttributeHydrator $attributeHydrator)
    {
        $this->attributeHydrator = $attributeHydrator;
    }

    public function hydrate(array $data): ShippingMethod
    {
        $id = (int) $data['__shippingMethod_id'];
        $translation = $this->getTranslation($data, '__shippingMethod', $this->mapping, $id);
        $data = array_merge($data, $translation);

        $service = new ShippingMethod(
            (int) $data['__shippingMethod_id'],
            (string) $data['__shippingMethod_name'],
            (int) $data['__shippingMethod_calculation'],
            (bool) $data['__shippingMethod_active'],
            (int) $data['__shippingMethod_position']
        );

        if (!empty($data['__shippingMethodAttribute_id'])) {
            $this->attributeHydrator->addAttribute($service, $data, 'shippingMethodAttribute');
        }

        return $service;
    }
}
