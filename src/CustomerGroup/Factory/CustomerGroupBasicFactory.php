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

namespace Shopware\CustomerGroup\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Extension\CustomerGroupExtension;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Framework\Factory\Factory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class CustomerGroupBasicFactory extends Factory
{
    const ROOT_NAME = 'customer_group';

    const FIELDS = [
       'uuid' => 'uuid',
       'display_gross' => 'display_gross',
       'input_gross' => 'input_gross',
       'has_global_discount' => 'has_global_discount',
       'percentage_global_discount' => 'percentage_global_discount',
       'minimum_order_amount' => 'minimum_order_amount',
       'minimum_order_amount_surcharge' => 'minimum_order_amount_surcharge',
       'name' => 'translation.name',
    ];

    /**
     * @var CustomerGroupExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        CustomerGroupBasicStruct $customerGroup,
        QuerySelection $selection,
        TranslationContext $context
    ): CustomerGroupBasicStruct {
        $customerGroup->setUuid((string) $data[$selection->getField('uuid')]);
        $customerGroup->setDisplayGross((bool) $data[$selection->getField('display_gross')]);
        $customerGroup->setInputGross((bool) $data[$selection->getField('input_gross')]);
        $customerGroup->setHasGlobalDiscount((bool) $data[$selection->getField('has_global_discount')]);
        $customerGroup->setPercentageGlobalDiscount(isset($data[$selection->getField('percentage_global_discount')]) ? (float) $data[$selection->getField('percentage_global_discount')] : null);
        $customerGroup->setMinimumOrderAmount(isset($data[$selection->getField('minimum_order_amount')]) ? (float) $data[$selection->getField('minimum_order_amount')] : null);
        $customerGroup->setMinimumOrderAmountSurcharge(isset($data[$selection->getField('minimum_order_amount_surcharge')]) ? (float) $data[$selection->getField('minimum_order_amount_surcharge')] : null);
        $customerGroup->setName((string) $data[$selection->getField('name')]);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($customerGroup, $data, $selection, $context);
        }

        return $customerGroup;
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
                'customer_group_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.customer_group_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
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
