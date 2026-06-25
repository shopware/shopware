<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Shopware\Core\Framework\Log\Package;

/**
 * One filesystem directory a YamlStyleOptionLoader scans, paired with its source label. Unlike the
 * element-type equivalent there is no name prefix: style option names are flat wire keys.
 *
 * @internal
 */
#[Package('framework')]
final readonly class StyleOptionSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
    ) {
    }
}
