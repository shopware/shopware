<?php declare(strict_types=1);

namespace SwagCustomAttributes;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;

class SwagCustomAttributes extends Plugin
{
    public function activate(ActivateContext $context): void
    {
        /** @var EntityRepositoryInterface $attributeSetRepository */
        $attributeSetRepository = $this->container->get('attribute_set.repository');
        $attributeSetRepository->upsert($this->getAttributeSets(), $context->getContext());

        /** @var EntityRepositoryInterface $attributeRepository */
        $attributeRepository = $this->container->get('attribute.repository');
        $attributeRepository->upsert($this->getAttributes(), $context->getContext());
    }

    public function deactivate(DeactivateContext $context): void
    {
        /**
         * We can safely delete the attribute set and the attributes.
         * The attribute data attached to the entities is not deleted.
         * Instead, it will not be hydrated anymore. After recreating
         * the attribute with the same name, the data will be accessible again.
         */
        $ids = [];
        foreach ($this->getAttributeSets() as $sets) {
            $ids[] = ['id' => $sets['id']];
        }
        /** @var EntityRepositoryInterface $attributeSetRepository */
        $attributeSetRepository = $this->container->get('attribute_set.repository');
        $attributeSetRepository->delete($ids, $context->getContext());

        $ids = [];
        foreach ($this->getAttributes() as $attributes) {
            $ids[] = ['id' => $attributes['id']];
        }
        /** @var EntityRepositoryInterface $attributeRepository */
        $attributeRepository = $this->container->get('attribute.repository');
        $attributeRepository->delete($ids, $context->getContext());
    }

    /**
     * These sets are visible in the administration
     */
    private function getAttributeSets(): array
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
            'attributes' => [
                [
                    'id' => 'c7e5e8604f8342878105ecd4df2d8646',
                    'name' => 'swag_backpack_size',
                    'type' => AttributeTypes::INT,
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
                    'type' => AttributeTypes::JSON,
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
                    'entityName' => ProductDefinition::getEntityName(),
                ],
                [
                    'id' => 'c7e5e8604f8342878105ecd4df2d8642',
                    'entityName' => CustomerDefinition::getEntityName(),
                ],
            ],
        ]];
    }

    /**
     * These attributes are NOT visible in the administration.
     */
    private function getAttributes(): array
    {
        return [
            [
                'id' => 'e7e5e8604f8342878105ecd4df2d8645',
                'name' => 'swag_custom_attribute',
                'type' => AttributeTypes::JSON,
            ],
        ];
    }
}
