<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * What a persisted mutation request writes to: the live layout, guarded by `expectedVersion` (its updatedAt), or a
 * draft, guarded by `expectedHeadRevisionId` (the id of the draft's head revision).
 *
 * @internal
 */
#[Package('framework')]
final readonly class MutationTarget
{
    private function __construct(
        public ?string $draftId,
        public ?string $expectedVersion,
        public ?string $expectedHeadRevisionId,
    ) {
    }

    public static function fromRequest(?string $draftId, ?string $expectedVersion, ?string $expectedHeadRevisionId): self
    {
        if ($draftId === null) {
            if ($expectedHeadRevisionId !== null) {
                throw ContentSystemException::unknownRequestField(['expectedHeadRevisionId']);
            }

            return new self(null, $expectedVersion, null);
        }

        if ($expectedVersion !== null) {
            throw ContentSystemException::unknownRequestField(['expectedVersion']);
        }

        if ($expectedHeadRevisionId === null) {
            throw ContentSystemException::missingRequestField(['expectedHeadRevisionId']);
        }

        return new self($draftId, null, $expectedHeadRevisionId);
    }

    /**
     * @phpstan-assert-if-true !null $this->draftId
     * @phpstan-assert-if-true !null $this->expectedHeadRevisionId
     */
    public function isDraft(): bool
    {
        return $this->draftId !== null;
    }
}
