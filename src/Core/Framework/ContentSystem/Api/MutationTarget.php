<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * What a persisted mutation request writes to: the live layout, guarded by `expectedVersion` (its updatedAt), or a
 * draft branch, guarded by `expectedHead` (the branch head revision id).
 *
 * @internal
 */
#[Package('framework')]
final readonly class MutationTarget
{
    private function __construct(
        public ?string $branchId,
        public ?string $expectedVersion,
        public ?string $expectedHead,
    ) {
    }

    public static function fromRequest(?string $branchId, ?string $expectedVersion, ?string $expectedHead): self
    {
        if ($branchId === null) {
            if ($expectedHead !== null) {
                throw ContentSystemException::unknownRequestField(['expectedHead']);
            }

            return new self(null, $expectedVersion, null);
        }

        if ($expectedVersion !== null) {
            throw ContentSystemException::unknownRequestField(['expectedVersion']);
        }

        if ($expectedHead === null) {
            throw ContentSystemException::missingRequestField(['expectedHead']);
        }

        return new self($branchId, null, $expectedHead);
    }

    /**
     * @phpstan-assert-if-true !null $this->branchId
     * @phpstan-assert-if-true !null $this->expectedHead
     */
    public function isBranch(): bool
    {
        return $this->branchId !== null;
    }
}
