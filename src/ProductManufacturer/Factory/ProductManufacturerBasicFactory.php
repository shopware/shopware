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

namespace Shopware\ProductManufacturer\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\ProductManufacturer\Extension\ProductManufacturerExtension;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ProductManufacturerBasicFactory extends Factory
{
    const ROOT_NAME = 'product_manufacturer';

    const FIELDS = [
       'uuid' => 'uuid',
       'link' => 'link',
       'media_uuid' => 'media_uuid',
       'updated_at' => 'updated_at',
       'name' => 'translation.name',
       'description' => 'translation.description',
       'meta_title' => 'translation.meta_title',
       'meta_description' => 'translation.meta_description',
       'meta_keywords' => 'translation.meta_keywords',
    ];

    /**
     * @var ProductManufacturerExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        ProductManufacturerBasicStruct $productManufacturer,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductManufacturerBasicStruct {
        $productManufacturer->setUuid((string) $data[$selection->getField('uuid')]);
        $productManufacturer->setLink((string) $data[$selection->getField('link')]);
        $productManufacturer->setMediaUuid(isset($data[$selection->getField('media_uuid')]) ? (string) $data[$selection->getField('media_uuid')] : null);
        $productManufacturer->setUpdatedAt(new \DateTime($data[$selection->getField('updated_at')]));
        $productManufacturer->setName((string) $data[$selection->getField('name')]);
        $productManufacturer->setDescription(isset($data[$selection->getField('description')]) ? (string) $data[$selection->getField('description')] : null);
        $productManufacturer->setMetaTitle(isset($data[$selection->getField('meta_title')]) ? (string) $data[$selection->getField('meta_title')] : null);
        $productManufacturer->setMetaDescription(isset($data[$selection->getField('meta_description')]) ? (string) $data[$selection->getField('meta_description')] : null);
        $productManufacturer->setMetaKeywords(isset($data[$selection->getField('meta_keywords')]) ? (string) $data[$selection->getField('meta_keywords')] : null);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($productManufacturer, $data, $selection, $context);
        }

        return $productManufacturer;
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
                'product_manufacturer_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.product_manufacturer_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
