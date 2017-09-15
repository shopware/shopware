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

namespace Shopware\ProductStream\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\ListingSorting\Factory\ListingSortingBasicFactory;
use Shopware\ListingSorting\Struct\ListingSortingBasicStruct;
use Shopware\ProductStream\Extension\ProductStreamExtension;
use Shopware\ProductStream\Struct\ProductStreamBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ProductStreamBasicFactory extends Factory
{
    const ROOT_NAME = 'product_stream';

    const FIELDS = [
       'uuid' => 'uuid',
       'name' => 'name',
       'conditions' => 'conditions',
       'type' => 'type',
       'description' => 'description',
       'listing_sorting_uuid' => 'listing_sorting_uuid',
    ];

    /**
     * @var ProductStreamExtension[]
     */
    protected $extensions = [];

    /**
     * @var ListingSortingBasicFactory
     */
    protected $listingSortingFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        ListingSortingBasicFactory $listingSortingFactory
    ) {
        parent::__construct($connection, $extensions);
        $this->listingSortingFactory = $listingSortingFactory;
    }

    public function hydrate(
        array $data,
        ProductStreamBasicStruct $productStream,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductStreamBasicStruct {
        $productStream->setUuid((string) $data[$selection->getField('uuid')]);
        $productStream->setName((string) $data[$selection->getField('name')]);
        $productStream->setConditions(isset($data[$selection->getField('conditions')]) ? (string) $data[$selection->getField('conditions')] : null);
        $productStream->setType(isset($data[$selection->getField('type')]) ? (int) $data[$selection->getField('type')] : null);
        $productStream->setDescription(isset($data[$selection->getField('description')]) ? (string) $data[$selection->getField('description')] : null);
        $productStream->setListingSortingUuid(isset($data[$selection->getField('listing_sorting_uuid')]) ? (string) $data[$selection->getField('listing_sorting_uuid')] : null);
        $listingSorting = $selection->filter('sorting');
        if ($listingSorting && !empty($data[$listingSorting->getField('uuid')])) {
            $productStream->setSorting(
                $this->listingSortingFactory->hydrate($data, new ListingSortingBasicStruct(), $listingSorting, $context)
            );
        }

        foreach ($this->extensions as $extension) {
            $extension->hydrate($productStream, $data, $selection, $context);
        }

        return $productStream;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['sorting'] = $this->listingSortingFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($listingSorting = $selection->filter('sorting')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'listing_sorting',
                $listingSorting->getRootEscaped(),
                sprintf('%s.uuid = %s.listing_sorting_uuid', $listingSorting->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->listingSortingFactory->joinDependencies($listingSorting, $query, $context);
        }

        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_stream_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.product_stream_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
        $fields['sorting'] = $this->listingSortingFactory->getAllFields();

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }
}
