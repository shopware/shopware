<?php
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

namespace Shopware\ShippingMethodPrice\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\ShippingMethodPrice\Extension\ShippingMethodPriceExtension;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicStruct;

class ShippingMethodPriceBasicFactory extends Factory
{
    const ROOT_NAME = 'shipping_method_price';

    const FIELDS = [
       'uuid' => 'uuid',
       'shipping_method_uuid' => 'shipping_method_uuid',
       'quantity_from' => 'quantity_from',
       'price' => 'price',
       'factor' => 'factor',
    ];

    /**
     * @var ShippingMethodPriceExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        ShippingMethodPriceBasicStruct $shippingMethodPrice,
        QuerySelection $selection,
        TranslationContext $context
    ): ShippingMethodPriceBasicStruct {
        $shippingMethodPrice->setUuid((string) $data[$selection->getField('uuid')]);
        $shippingMethodPrice->setShippingMethodUuid((string) $data[$selection->getField('shipping_method_uuid')]);
        $shippingMethodPrice->setQuantityFrom((float) $data[$selection->getField('quantity_from')]);
        $shippingMethodPrice->setPrice((float) $data[$selection->getField('price')]);
        $shippingMethodPrice->setFactor((float) $data[$selection->getField('factor')]);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($shippingMethodPrice, $data, $selection, $context);
        }

        return $shippingMethodPrice;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'shipping_method_price_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.shipping_method_price_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }
}
