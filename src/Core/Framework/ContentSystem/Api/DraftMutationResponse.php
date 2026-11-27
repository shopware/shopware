<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Mutation\DraftMutationResult;
use Shopware\Core\Framework\Log\Package;

/**
 * The mutation response of a draft mutation, plus the draft it moved; its `headRevisionId` is the revision the mutation
 * appended and the `expectedHeadRevisionId` of the next edit.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Api\ContentLayoutMutationControllerTest
 */
#[Package('framework')]
final readonly class DraftMutationResponse implements \JsonSerializable
{
    private function __construct(
        private DraftMutationResult $result,
        private MutationResponse $mutation,
    ) {
    }

    public static function fromResult(DraftMutationResult $result, StoredElementCodec $elementCodec): self
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
            'draft' => LayoutDraftNormalizer::draft($this->result->draft),
        ];
    }
}
