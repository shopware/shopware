<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext;

use Shopware\Core\Framework\Log\Package;

/**
 * Represents a single item on the data context stack.
 *
 * @internal
 */
#[Package('discovery')]
final readonly class DataContextStackItem
{
    public function __construct(
        public mixed $data,
        public string $distribution,
    ) {
    }
}
