<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * Class-level constraint asserting a binding specification declaration is consistent with its declared
 * element type: the type is registered, every `resolves` key names a reference property the type actually
 * has, every configured loader produces a type assignable to that property's declared FQCN, and every
 * `inputs` key names a primitive property with a type-matching default. Runs against live registries
 * (element types, data loaders), unlike {@see WellFormedBindingSpecification} which checks shape only.
 *
 * @internal
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TypeConsistentBindingSpecification extends Constraint
{
    public string $unknownTypeMessage = 'type "{{ type }}" is not a registered element type';

    public string $resolvesEntryNotReferencePropertyMessage = 'resolves entry "{{ key }}" does not name a reference property of type "{{ type }}"';

    public string $resolvesEntryConfigMessage = 'resolves entry "{{ key }}" config is invalid: {{ reason }}';

    public string $resolvesEntryLoaderNotRegisteredMessage = 'resolves entry "{{ key }}" names loader "{{ loader }}", which is not a registered data loader';

    public string $resolvesEntryNotAssignableMessage = 'resolves entry "{{ key }}" loader produces "{{ producedType }}", which is not assignable to the declared property type "{{ declaredType }}"';

    public string $resolvesEntryEntityPropertyNotPrimitiveMessage = 'resolves entry "{{ key }}" entity loader "property" must name a primitive property of type "{{ type }}"';

    public string $resolvesEntryContextFormMessage = 'resolves entry "{{ key }}" uses the "context" form, which is not yet supported';

    public string $inputsEntryNotPrimitivePropertyMessage = 'inputs entry "{{ key }}" does not name a primitive property of type "{{ type }}"';

    public string $inputsEntryDefaultTypeMessage = 'inputs entry "{{ key }}" default value must match the declared type "{{ type }}"';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
