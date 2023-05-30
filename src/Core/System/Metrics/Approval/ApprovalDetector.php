<?php declare(strict_types=1);

namespace Shopware\Core\System\Metrics\Approval;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('merchant-services')]
class ApprovalDetector
{
    public function __construct(private readonly bool $needsApprovalRequest)
    {
    }

    public function needsApprovalRequest(): bool
    {
        return $this->needsApprovalRequest;
    }
}
