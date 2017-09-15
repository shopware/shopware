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

namespace Shopware\ProductDetail\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;
use Shopware\ProductDetail\Struct\ProductDetailDetailStruct;
use Shopware\ProductPrice\Factory\ProductPriceBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Unit\Factory\UnitBasicFactory;

class ProductDetailDetailFactory extends ProductDetailBasicFactory
{
    /**
     * @var ProductPriceBasicFactory
     */
    protected $productPriceFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        ProductPriceBasicFactory $productPriceFactory,
        UnitBasicFactory $unitFactory
    ) {
        parent::__construct($connection, $extensions, $unitFactory);
        $this->productPriceFactory = $productPriceFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        ProductDetailBasicStruct $productDetail,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductDetailBasicStruct {
        /** @var ProductDetailDetailStruct $productDetail */
        $productDetail = parent::hydrate($data, $productDetail, $selection, $context);

        return $productDetail;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($prices = $selection->filter('prices')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_price',
                $prices->getRootEscaped(),
                sprintf('%s.uuid = %s.product_detail_uuid', $selection->getRootEscaped(), $prices->getRootEscaped())
            );

            $this->productPriceFactory->joinDependencies($prices, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['prices'] = $this->productPriceFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->extensions as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
