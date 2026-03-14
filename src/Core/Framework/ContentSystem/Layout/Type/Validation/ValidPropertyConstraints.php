<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ValidPropertyConstraints extends Constraint
{
    public string $translatableMessage = 'translatable is only valid with type "string"';

    public string $enumTypeMessage = 'enum is only valid with primitive types (string, integer, boolean, number)';

    public string $enumListMessage = 'enum must be a list';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
