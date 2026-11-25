<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * Body of the discard-draft action.
 *
 * @internal
 */
#[Package('framework')]
final class ContentLayoutDiscardDraftRequest
{
    public function __construct(
        public readonly string $draftId,
    ) {
    }
}
