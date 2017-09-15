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

namespace Shopware\Holiday\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Holiday\Extension\HolidayExtension;
use Shopware\Holiday\Struct\HolidayBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class HolidayBasicFactory extends Factory
{
    const ROOT_NAME = 'holiday';

    const FIELDS = [
       'uuid' => 'uuid',
       'calculation' => 'calculation',
       'event_date' => 'event_date',
       'name' => 'translation.name',
    ];

    /**
     * @var HolidayExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        HolidayBasicStruct $holiday,
        QuerySelection $selection,
        TranslationContext $context
    ): HolidayBasicStruct {
        $holiday->setUuid((string) $data[$selection->getField('uuid')]);
        $holiday->setCalculation((string) $data[$selection->getField('calculation')]);
        $holiday->setEventDate(new \DateTime($data[$selection->getField('event_date')]));
        $holiday->setName((string) $data[$selection->getField('name')]);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($holiday, $data, $selection, $context);
        }

        return $holiday;
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
                'holiday_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.holiday_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
