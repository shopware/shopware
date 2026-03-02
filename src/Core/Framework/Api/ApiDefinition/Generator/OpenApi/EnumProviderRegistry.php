<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class EnumProviderRegistry
{
    /**
     * @param iterable<FieldEnumProviderInterface> $enumProviders
     */
    public function __construct(
        private readonly iterable $enumProviders = []
    ) {
    }

    /**
     * @return list<string|bool|int|float>
     */
    public function getChoices(EntityDefinition $definition, Field $field): array
    {
        $choices = [];

        $choice = $field->getFlag(Choice::class);
        if ($choice instanceof Choice) {
            $choices = $choice->getChoices();
        }

        foreach ($this->enumProviders as $enumProvider) {
            if (!$enumProvider->isSupported($definition->getEntityName(), $field->getPropertyName())) {
                continue;
            }

            $choices = array_merge($choices, $enumProvider->getChoices());
        }

        return array_values(array_unique($choices, \SORT_REGULAR));
    }
}
