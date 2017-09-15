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

namespace Shopware\Product\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Category\Factory\CategoryBasicFactory;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\PriceGroup\Factory\PriceGroupBasicFactory;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\Product\Struct\ProductDetailStruct;
use Shopware\ProductDetail\Factory\ProductDetailDetailFactory;
use Shopware\ProductManufacturer\Factory\ProductManufacturerBasicFactory;
use Shopware\ProductVote\Factory\ProductVoteBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\SeoUrl\Factory\SeoUrlBasicFactory;
use Shopware\Tax\Factory\TaxBasicFactory;

class ProductDetailFactory extends ProductBasicFactory
{
    /**
     * @var ProductDetailDetailFactory
     */
    protected $productDetailFactory;

    /**
     * @var CategoryBasicFactory
     */
    protected $categoryFactory;

    /**
     * @var ProductVoteBasicFactory
     */
    protected $productVoteFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        ProductDetailDetailFactory $productDetailFactory,
        CategoryBasicFactory $categoryFactory,
        ProductVoteBasicFactory $productVoteFactory,
        ProductManufacturerBasicFactory $productManufacturerFactory,
        TaxBasicFactory $taxFactory,
        SeoUrlBasicFactory $seoUrlFactory,
        PriceGroupBasicFactory $priceGroupFactory,
        CustomerGroupBasicFactory $customerGroupFactory
    ) {
        parent::__construct($connection, $extensions, $productManufacturerFactory, $productDetailFactory, $taxFactory, $seoUrlFactory, $priceGroupFactory, $customerGroupFactory);
        $this->productDetailFactory = $productDetailFactory;
        $this->categoryFactory = $categoryFactory;
        $this->productVoteFactory = $productVoteFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());
        $fields['_sub_select_category_uuids'] = '_sub_select_category_uuids';

        return $fields;
    }

    public function hydrate(
        array $data,
        ProductBasicStruct $product,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductBasicStruct {
        /** @var ProductDetailStruct $product */
        $product = parent::hydrate($data, $product, $selection, $context);

        if ($selection->hasField('_sub_select_category_uuids')) {
            $uuids = explode('|', $data[$selection->getField('_sub_select_category_uuids')]);
            $product->setCategoryUuids(array_filter($uuids));
        }

        return $product;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($details = $selection->filter('details')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_detail',
                $details->getRootEscaped(),
                sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $details->getRootEscaped())
            );

            $this->productDetailFactory->joinDependencies($details, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($categories = $selection->filter('categories')) {
            $mapping = QuerySelection::escape($categories->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_category_ro',
                $mapping,
                sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $mapping)
            );
            $query->leftJoin(
                $mapping,
                'category',
                $categories->getRootEscaped(),
                sprintf('%s.category_uuid = %s.uuid', $mapping, $categories->getRootEscaped())
            );

            $this->categoryFactory->joinDependencies($categories, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($selection->hasField('_sub_select_category_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.category_uuid SEPARATOR \'|\')
                    FROM product_category_ro mapping
                    WHERE mapping.product_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_category_uuids'))
            );
        }

        if ($votes = $selection->filter('votes')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_vote',
                $votes->getRootEscaped(),
                sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $votes->getRootEscaped())
            );

            $this->productVoteFactory->joinDependencies($votes, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['details'] = $this->productDetailFactory->getAllFields();
        $fields['categories'] = $this->categoryFactory->getAllFields();
        $fields['votes'] = $this->productVoteFactory->getAllFields();

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
