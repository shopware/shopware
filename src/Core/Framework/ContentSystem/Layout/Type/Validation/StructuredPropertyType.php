<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal only for use by the content-system element types
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class StructuredPropertyType extends Constraint
{
    public string $typeListMessage = 'type must be a non-empty list when multiple types are configured';

    public string $typeEntryMessage = 'all declared types must be non-empty strings';

    public string $duplicateTypeMessage = 'type list must not contain duplicate entries';

    public string $objectRequiresPropertiesMessage = 'properties are required when type includes "object"';

    public string $propertiesRequireObjectTypeMessage = 'properties are only allowed when type includes "object"';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
