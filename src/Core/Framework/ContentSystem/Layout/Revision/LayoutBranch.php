<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Shopware\Core\Framework\Log\Package;

/**
 * A named pointer into the revision graph: `base` is the revision it was opened from, `head` the latest one
 * saved on it, and `live` the revision that was published when it was opened — the state publishing it
 * replaces, see {@see LayoutRevisionService::publish()}.
 *
 * @internal
 */
#[Package('framework')]
final readonly class LayoutBranch
{
    public function __construct(
        public string $id,
        public string $name,
        public string $base,
        public string $head,
        public string $live,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public function withHead(string $head, \DateTimeImmutable $updatedAt): self
    {
        return new self($this->id, $this->name, $this->base, $head, $this->live, $this->createdAt, $updatedAt);
    }
}
