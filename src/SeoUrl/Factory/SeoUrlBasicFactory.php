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

namespace Shopware\SeoUrl\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\SeoUrl\Extension\SeoUrlExtension;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;

class SeoUrlBasicFactory extends Factory
{
    const ROOT_NAME = 'seo_url';

    const FIELDS = [
       'uuid' => 'uuid',
       'seo_hash' => 'seo_hash',
       'shop_uuid' => 'shop_uuid',
       'name' => 'name',
       'foreign_key' => 'foreign_key',
       'path_info' => 'path_info',
       'seo_path_info' => 'seo_path_info',
       'is_canonical' => 'is_canonical',
       'created_at' => 'created_at',
    ];

    /**
     * @var SeoUrlExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        SeoUrlBasicStruct $seoUrl,
        QuerySelection $selection,
        TranslationContext $context
    ): SeoUrlBasicStruct {
        $seoUrl->setUuid((string) $data[$selection->getField('uuid')]);
        $seoUrl->setSeoHash((string) $data[$selection->getField('seo_hash')]);
        $seoUrl->setShopUuid((string) $data[$selection->getField('shop_uuid')]);
        $seoUrl->setName((string) $data[$selection->getField('name')]);
        $seoUrl->setForeignKey((string) $data[$selection->getField('foreign_key')]);
        $seoUrl->setPathInfo((string) $data[$selection->getField('path_info')]);
        $seoUrl->setSeoPathInfo((string) $data[$selection->getField('seo_path_info')]);
        $seoUrl->setIsCanonical((bool) $data[$selection->getField('is_canonical')]);
        $seoUrl->setCreatedAt(new \DateTime($data[$selection->getField('created_at')]));

        foreach ($this->extensions as $extension) {
            $extension->hydrate($seoUrl, $data, $selection, $context);
        }

        return $seoUrl;
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
                'seo_url_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.seo_url_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
