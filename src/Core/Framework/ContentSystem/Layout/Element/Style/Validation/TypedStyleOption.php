<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * Class-level constraint asserting a style option declaration is internally consistent. One cohesive
 * constraint rather than one per facet, because every rule shares the same primitive-type premise.
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

    public string $defaultEnumMessage = 'default must be one of the enum values';

    public string $defaultRangeMessage = 'default must be within the declared range';

    public string $defaultMaxLengthMessage = 'default must not exceed maxLength';

    public string $adminUiArrayMessage = 'adminUI must be an array';

    public string $breakpointAwareTypeMessage = 'breakpointAware must be a boolean';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
