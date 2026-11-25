<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Mutation\BranchMutationResult;
use Shopware\Core\Framework\Log\Package;

/**
 * The mutation response of a branch mutation, plus the revision it appended and the branch it moved.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Api\ContentLayoutMutationControllerTest
 */
#[Package('framework')]
final readonly class BranchMutationResponse implements \JsonSerializable
{
    private function __construct(
        private BranchMutationResult $result,
        private MutationResponse $mutation,
    ) {
    }

    public static function fromResult(BranchMutationResult $result, StoredElementCodec $elementCodec): self
    {
        return new self($result, MutationResponse::fromResult($result->mutation, $elementCodec));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...$this->mutation->jsonSerialize(),
            'revision' => LayoutRevisionNormalizer::revision($this->result->revision, [$this->result->branch->id], published: false),
            'branch' => LayoutRevisionNormalizer::branch($this->result->branch),
        ];
    }
}
