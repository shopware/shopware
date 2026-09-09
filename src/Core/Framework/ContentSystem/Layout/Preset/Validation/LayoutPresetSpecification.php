<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal only for use by the content-system layout presets
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class LayoutPresetSpecification extends Constraint
{
    public string $layoutMessage = 'The "layout" field must be a list of elements.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
