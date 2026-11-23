<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal only for use by the content-system element types
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TranslatableType extends Constraint
{
    public string $message = 'translatable is only valid on a lone primitive type';

    public string $nestedMessage = 'translatable is only valid on a top-level property, not on a member nested inside an object property';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
