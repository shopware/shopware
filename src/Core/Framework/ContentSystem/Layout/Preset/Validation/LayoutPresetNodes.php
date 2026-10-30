<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal only for use by the content-system layout presets
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class LayoutPresetNodes extends Constraint
{
    public string $nodeMessage = 'A layout node must be a mapping.';

    public string $missingMessage = 'A layout node must declare either "component" or "preset".';

    public string $ambiguousMessage = 'A layout node must declare either "component" or "preset", not both.';

    public string $presetTypeMessage = 'A "preset" reference must be a non-empty string.';

    public string $presetPropertiesMessage = 'A "preset" reference takes no other properties, got: {{ properties }}.';
}
