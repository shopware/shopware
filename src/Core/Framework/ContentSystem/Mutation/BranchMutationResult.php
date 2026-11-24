<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutBranch;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevision;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class BranchMutationResult
{
    public function __construct(
        public MutationResult $mutation,
        public LayoutRevision $revision,
        public LayoutBranch $branch,
    ) {
    }
}
