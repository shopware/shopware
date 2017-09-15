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

namespace Shopware\ListingSorting\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\ListingSorting\Extension\ListingSortingExtension;
use Shopware\ListingSorting\Struct\ListingSortingBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ListingSortingBasicFactory extends Factory
{
    const ROOT_NAME = 'listing_sorting';

    const FIELDS = [
       'uuid' => 'uuid',
       'active' => 'active',
       'display_in_categories' => 'display_in_categories',
       'position' => 'position',
       'payload' => 'payload',
       'label' => 'translation.label',
    ];

    /**
     * @var ListingSortingExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        ListingSortingBasicStruct $listingSorting,
        QuerySelection $selection,
        TranslationContext $context
    ): ListingSortingBasicStruct {
        $listingSorting->setUuid((string) $data[$selection->getField('uuid')]);
        $listingSorting->setActive((bool) $data[$selection->getField('active')]);
        $listingSorting->setDisplayInCategories((bool) $data[$selection->getField('display_in_categories')]);
        $listingSorting->setPosition((int) $data[$selection->getField('position')]);
        $listingSorting->setPayload((string) $data[$selection->getField('payload')]);
        $listingSorting->setLabel((string) $data[$selection->getField('label')]);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($listingSorting, $data, $selection, $context);
        }

        return $listingSorting;
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
                'listing_sorting_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.listing_sorting_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
