<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * Class-level semantic constraint: validates a binding specification against the live element-type
 * registry and data-loader config serializers.
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
