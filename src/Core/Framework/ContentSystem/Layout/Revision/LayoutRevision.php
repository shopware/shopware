<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class LayoutRevision
{
    /**
     * @param list<StoredElement> $tree
     */
    public function __construct(
        public string $id,
        public ?string $parent,
        public \DateTimeImmutable $createdAt,
        public ?string $createdBy,
        public array $tree,
    ) {
    }
}
