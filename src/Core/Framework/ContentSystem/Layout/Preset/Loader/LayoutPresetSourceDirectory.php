<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class LayoutPresetSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
        public string $prefix,
    ) {
    }
}
