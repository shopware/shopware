<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ExportLimits
{
    public function __construct(
        public int $depth,
        public int $items,
        public int $string,
    ) {
    }

    public static function summary(): self
    {
        return new self(depth: 3, items: 5, string: 120);
    }

    public static function full(): self
    {
        return new self(depth: 12, items: 1000, string: 10000);
    }
}
