<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemLayoutPresetLoader
{
    /**
     * @return list<ContentSystemLayoutPresetSpecification>
     */
    abstract public function load(): array;
}
