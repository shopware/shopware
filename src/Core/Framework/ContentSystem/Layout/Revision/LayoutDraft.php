<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Shopware\Core\Framework\Log\Package;

/**
 * A named pointer into the revision graph: `base` is the revision it was opened from, `head` the latest one
 * saved on it, and `liveAtOpen` the live revision when it was opened. The publish rule reads `liveAtOpen`, see
 * {@see LayoutRevisionService::publish()}; `base` keeps the origin of a rollback or branched draft.
 *
 * @internal
 */
#[Package('framework')]
final readonly class LayoutDraft
{
    public function __construct(
        public string $id,
        public string $name,
        public string $base,
        public string $head,
        public string $liveAtOpen,
        public \DateTimeImmutable $createdAt,
        public ?string $createdBy,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public function withHead(string $head, \DateTimeImmutable $updatedAt): self
    {
        return new self($this->id, $this->name, $this->base, $head, $this->liveAtOpen, $this->createdAt, $this->createdBy, $updatedAt);
    }
}
