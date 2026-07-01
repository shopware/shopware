<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * Class-level constraint asserting a binding specification declaration is structurally well-formed.
 * One cohesive constraint rather than one per facet, because every rule shares the same raw-shape
 * premise. Checks structure only — no element-type-registry or data-loader lookups; a later
 * constraint enforces those against live registries.
 *
 * @internal
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class WellFormedBindingSpecification extends Constraint
{
    public string $typeBlankMessage = 'type must not be blank';

    public string $labelBlankMessage = 'label must not be blank';

    public string $resolvesArrayMessage = 'resolves must be an array';

    public string $resolvesEntryArrayMessage = 'resolves entry "{{ key }}" must be an array';

    public string $resolvesEntryLoaderMessage = 'resolves entry "{{ key }}" must declare a non-blank "loader"';

    public string $resolvesEntryConfigMessage = 'resolves entry "{{ key }}" "config" must be an array';

    public string $inputsArrayMessage = 'inputs must be an array';

    public string $inputsEntryArrayMessage = 'inputs entry "{{ key }}" must be an array';

    public string $inputsEntryDefaultMessage = 'inputs entry "{{ key }}" "default" must be a scalar or null';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
