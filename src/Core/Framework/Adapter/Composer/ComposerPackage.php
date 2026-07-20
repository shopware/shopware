<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Composer;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class ComposerPackage
{
    public function __construct(
        public string $name,
        public string $version,
        public string $prettyVersion,
        public string $path,
    ) {
    }
}
