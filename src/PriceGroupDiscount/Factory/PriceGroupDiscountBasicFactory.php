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

namespace Shopware\PriceGroupDiscount\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\PriceGroupDiscount\Extension\PriceGroupDiscountExtension;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class PriceGroupDiscountBasicFactory extends Factory
{
    const ROOT_NAME = 'price_group_discount';

    const FIELDS = [
       'uuid' => 'uuid',
       'price_group_uuid' => 'price_group_uuid',
       'customer_group_uuid' => 'customer_group_uuid',
       'percentage_discount' => 'percentage_discount',
       'product_count' => 'product_count',
    ];

    /**
     * @var PriceGroupDiscountExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        PriceGroupDiscountBasicStruct $priceGroupDiscount,
        QuerySelection $selection,
        TranslationContext $context
    ): PriceGroupDiscountBasicStruct {
        $priceGroupDiscount->setUuid((string) $data[$selection->getField('uuid')]);
        $priceGroupDiscount->setPriceGroupUuid((string) $data[$selection->getField('price_group_uuid')]);
        $priceGroupDiscount->setCustomerGroupUuid((string) $data[$selection->getField('customer_group_uuid')]);
        $priceGroupDiscount->setPercentageDiscount((float) $data[$selection->getField('percentage_discount')]);
        $priceGroupDiscount->setProductCount((float) $data[$selection->getField('product_count')]);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($priceGroupDiscount, $data, $selection, $context);
        }

        return $priceGroupDiscount;
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
                'price_group_discount_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.price_group_discount_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
