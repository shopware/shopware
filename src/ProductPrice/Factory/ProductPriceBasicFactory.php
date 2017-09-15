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

namespace Shopware\ProductPrice\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\ProductPrice\Extension\ProductPriceExtension;
use Shopware\ProductPrice\Struct\ProductPriceBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ProductPriceBasicFactory extends Factory
{
    const ROOT_NAME = 'product_price';

    const FIELDS = [
       'uuid' => 'uuid',
       'customer_group_uuid' => 'customer_group_uuid',
       'quantity_start' => 'quantity_start',
       'quantity_end' => 'quantity_end',
       'product_detail_uuid' => 'product_detail_uuid',
       'price' => 'price',
       'pseudo_price' => 'pseudo_price',
       'base_price' => 'base_price',
       'percentage' => 'percentage',
    ];

    /**
     * @var ProductPriceExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        ProductPriceBasicStruct $productPrice,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductPriceBasicStruct {
        $productPrice->setUuid((string) $data[$selection->getField('uuid')]);
        $productPrice->setCustomerGroupUuid((string) $data[$selection->getField('customer_group_uuid')]);
        $productPrice->setQuantityStart((int) $data[$selection->getField('quantity_start')]);
        $productPrice->setQuantityEnd(isset($data[$selection->getField('quantity_end')]) ? (int) $data[$selection->getField('quantity_end')] : null);
        $productPrice->setProductDetailUuid((string) $data[$selection->getField('product_detail_uuid')]);
        $productPrice->setPrice((float) $data[$selection->getField('price')]);
        $productPrice->setPseudoPrice(isset($data[$selection->getField('pseudo_price')]) ? (float) $data[$selection->getField('pseudo_price')] : null);
        $productPrice->setBasePrice(isset($data[$selection->getField('base_price')]) ? (float) $data[$selection->getField('base_price')] : null);
        $productPrice->setPercentage(isset($data[$selection->getField('percentage')]) ? (float) $data[$selection->getField('percentage')] : null);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($productPrice, $data, $selection, $context);
        }

        return $productPrice;
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
                'product_price_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.product_price_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
