<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal only for use by the content-system element types
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TypedEnum extends Constraint
{
    public string $typeMessage = 'enum is only valid with primitive types (string, integer, boolean, number)';

    public string $listMessage = 'enum must be a list';

    public string $valueTypeMessage = 'all enum values must match the declared type "{{ type }}"';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
