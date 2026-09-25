<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal only for use by the content-system element types
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class InlineMappableType extends Constraint
{
    public string $message = 'inlineMappable is only valid with type "string"';

    public string $exclusiveMessage = 'inlineMappable and mappable are mutually exclusive on one property';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
