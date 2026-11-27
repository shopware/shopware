<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Shopware\Core\Framework\Log\Package;

/**
 * An immutable saved state of a layout. Its tree lives in its own row and is read only where a caller needs
 * it, see {@see LayoutRevisionStore::tree()}. `publishedAt` and `publishedBy` describe the first time it went
 * live and `lastLiveAt` the latest, `null` if never; `publishedBy` is also `null` for a layout write that
 * bypassed publishing.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionServiceTest
 */
#[Package('framework')]
final readonly class LayoutRevision
{
    public function __construct(
        public string $id,
        public ?string $parent,
        public \DateTimeImmutable $createdAt,
        public ?string $createdBy,
        public ?\DateTimeImmutable $publishedAt = null,
        public ?string $publishedBy = null,
        public ?\DateTimeImmutable $lastLiveAt = null,
    ) {
    }
}
