<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * Class-level structural-shape constraint, no registry lookups. One cohesive constraint rather than
 * one per facet, because every rule shares the same raw-shape premise.
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

    public string $inputsEntryRequiredMessage = 'inputs entry "{{ key }}" must carry a boolean "required"';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
