<?php declare(strict_types=1);

namespace SwagCustomCustomFields;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\CustomField\CustomFieldTypes;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;

class SwagCustomFields extends Plugin
{
    public function activate(ActivateContext $context): void
    {
        /** @var EntityRepositoryInterface $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $customFieldSetRepository->upsert($this->getCustomFieldSets(), $context->getContext());

        /** @var EntityRepositoryInterface $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field.repository');
        $customFieldRepository->upsert($this->getCustomFields(), $context->getContext());
    }

    public function deactivate(DeactivateContext $context): void
    {
        /**
         * We can safely delete the customField set and the customFields.
         * The customField data attached to the entities is not deleted.
         * Instead, it will not be hydrated anymore. After recreating
         * the custom field with the same name, the data will be accessible again.
         */
        $ids = [];
        foreach ($this->getCustomFieldSets() as $sets) {
            $ids[] = ['id' => $sets['id']];
        }
        /** @var EntityRepositoryInterface $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $customFieldSetRepository->delete($ids, $context->getContext());

        $ids = [];
        foreach ($this->getCustomFields() as $customFields) {
            $ids[] = ['id' => $customFields['id']];
        }
        /** @var EntityRepositoryInterface $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field.repository');
        $customFieldRepository->delete($ids, $context->getContext());
    }

    /**
     * These sets are visible in the administration
     */
    private function getCustomFieldSets(): array
    {
        return [[
            'id' => 'd7e5e8604f8342878105ecd4df2d8645',
            'name' => 'swag_backpack',
            'config' => [
                'label' => [
                    'de-DE' => 'Rucksack',
                    'en-GB' => 'Backpack',
                ],
            ],
            'customFields' => [
                [
                    'id' => 'c7e5e8604f8342878105ecd4df2d8646',
                    'name' => 'swag_backpack_size',
                    'type' => CustomFieldTypes::INT,
                    'config' => [
                        'componentName' => 'sw-field',
                        'type' => 'number',
                        'numberType' => 'int',
                        'label' => [
                            'de-DE' => 'Größe',
                            'en-GB' => 'Size',
                        ],
                    ],
                ],
                [
                    'id' => 'c7e5e8604f8342878105ecd4df2d8647',
                    'name' => 'swag_backpack_color',
                    'type' => CustomFieldTypes::JSON,
                    'config' => [
                        'componentName' => 'swag-radio',
                        'options' => [
                            [
                                'id' => 'red',
                                'name' => [
                                    'de-DE' => 'Rot',
                                    'en-GB' => 'Red',
                                ],
                            ],
                            [
                                'id' => 'blue',
                                'name' => [
                                    'de-DE' => 'Blau',
                                    'en-GB' => 'Blue',
                                ],
                            ],
                        ],
                        'label' => [
                            'de-DE' => 'Farbe',
                            'en-GB' => 'Color',
                        ],
                    ],
                ],
            ],
            'relations' => [
                [
                    'id' => 'c7e5e8604f8342878105ecd4df2d8641',
                    'entityName' => $this->container->get(ProductDefinition::class)->getEntityName(),
                ],
                [
                    'id' => 'c7e5e8604f8342878105ecd4df2d8642',
                    'entityName' => $this->container->get(CustomerDefinition::class)->getEntityName(),
                ],
            ],
        ]];
    }

    /**
     * These customFields are NOT visible in the administration.
     */
    private function getCustomFields(): array
    {
        return [
            [
                'id' => 'e7e5e8604f8342878105ecd4df2d8645',
                'name' => 'swag_custom_custom_field',
                'type' => CustomFieldTypes::JSON,
            ],
        ];
    }
}
