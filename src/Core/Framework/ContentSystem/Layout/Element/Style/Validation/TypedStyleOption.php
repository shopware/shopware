<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * Asserts that a style option declaration is internally consistent: its enum, range, maxLength,
 * and default all agree with the declared primitive type. Cohesive single constraint rather than
 * one per facet, because the rules share the same primitive-type premise.
 *
 * @internal
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TypedStyleOption extends Constraint
{
    public string $enumArrayMessage = 'enum must be an array';

    public string $enumTypeMessage = 'enum values must all match the declared type "{{ type }}"';

    public string $enumListMessage = 'enum must be a list';

    public string $enumEmptyMessage = 'enum must not be empty';

    public string $rangeArrayMessage = 'range must be an array';

    public string $rangeTypeMessage = 'range is only valid for the numeric types "integer" and "number"';

    public string $rangeBoundsMessage = 'range bounds must be numeric and min must not exceed max';

    public string $maxLengthTypeMessage = 'maxLength is only valid for the "string" type';

    public string $maxLengthValueMessage = 'maxLength must be a positive integer';

    public string $defaultTypeMessage = 'default must match the declared type "{{ type }}"';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
