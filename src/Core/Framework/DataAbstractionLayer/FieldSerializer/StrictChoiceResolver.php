<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class StrictChoiceResolver
{
    /**
     * @param iterable<FieldEnumProviderInterface> $enumProviders
     */
    public function __construct(private iterable $enumProviders = [])
    {
    }

    /**
     * Returns the values accepted by a strict `Choice` field: the flag's choices plus the choices of every enum provider that supports the field.
     * Returns null when the field has no strict `Choice` flag.
     *
     * Enum providers are asked for the entity the field is exposed on, like OpenApiDefinitionSchemaBuilder does:
     * a translated field is written through the `*_translation` definition, but providers and API consumers know it by the parent entity.
     *
     * @return list<string|bool|int|float>|null
     */
    public function getStrictChoices(Field $field, EntityDefinition $definition): ?array
    {
        $choice = $field->getFlag(Choice::class);
        if (!$choice instanceof Choice || !$choice->isStrict() || $choice->getChoices() === []) {
            return null;
        }

        $entityName = $definition instanceof EntityTranslationDefinition
            ? $definition->getParentDefinition()->getEntityName()
            : $definition->getEntityName();

        $choices = $choice->getChoices();
        foreach ($this->enumProviders as $enumProvider) {
            if ($enumProvider->isSupported($entityName, $field->getPropertyName())) {
                $choices = [...$choices, ...$enumProvider->getChoices()];
            }
        }

        return $choices;
    }
}
