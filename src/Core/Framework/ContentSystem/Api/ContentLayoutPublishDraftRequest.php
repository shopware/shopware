<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * Body of the publish-draft action. `expectedHeadRevisionId` is the draft head revision id as last read.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContentLayoutPublishDraftRequest
{
    public function __construct(
        public string $draftId,
        public string $expectedHeadRevisionId,
    ) {
    }
}
