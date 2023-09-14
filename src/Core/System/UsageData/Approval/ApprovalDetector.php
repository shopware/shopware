<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Approval;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('merchant-services')]
class ApprovalDetector
{
    public const SYSTEM_CONFIG_KEY_SHARE_DATA = 'core.usage_data.shareUsageData';

    public function __construct(
        private readonly bool $needsApprovalRequest,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function needsApprovalRequest(): bool
    {
        return $this->needsApprovalRequest;
    }

    public function isApprovalAlreadyRequested(): bool
    {
        return $this->systemConfigService->get(ApprovalDetector::SYSTEM_CONFIG_KEY_SHARE_DATA) !== null;
    }

    public function isApprovalGiven(): bool
    {
        return $this->systemConfigService->getBool(ApprovalDetector::SYSTEM_CONFIG_KEY_SHARE_DATA);
    }
}
