<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Module;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
final readonly class MainModule
{
    public function __construct(
        public string $source,
    ) {
    }
}
