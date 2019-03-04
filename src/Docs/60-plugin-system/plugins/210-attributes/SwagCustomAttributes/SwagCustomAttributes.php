<?php declare(strict_types=1);

namespace SwagCustomAttributes;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class SwagCustomAttributes extends Plugin
{
    public function __construct(bool $active = true, ?string $path = null)
    {
        parent::__construct($active, $path);
    }

    public function install(InstallContext $context): void
    {
    }

    /**
     * These sets are visible in the administration
     */
    public function getAttributeSets(): array
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
                    'entityName' => ProductDefinition::getEntityName(),
                ],
                [
                    'entityName' => CustomerDefinition::getEntityName(),
                ],
            ],
        ]];
    }

    /**
     * These attributes are NOT visible in the administration.
     */
    public function getAttributes(): array
    {
        return [
            [
                'id' => 'e7e5e8604f8342878105ecd4df2d8645',
                'name' => 'swag_custom_attribute',
                'type' => AttributeTypes::JSON,
            ],
        ];
    }

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
}
