<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * For the persisted mutation request DTOs, which all declare `branchId`, `expectedVersion` and `expectedHead`.
 *
 * @internal
 */
#[Package('framework')]
trait TargetsMutation
{
    public function target(): MutationTarget
    {
        return MutationTarget::fromRequest($this->branchId, $this->expectedVersion, $this->expectedHead);
    }
}
