<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemLayoutPresetLoader
{
    /**
     * @return array<string, LayoutPresetSpecificationDto> validated presets keyed by resolved id
     */
    abstract public function load(): array;
}
