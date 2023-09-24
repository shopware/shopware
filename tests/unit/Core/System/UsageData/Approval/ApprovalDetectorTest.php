<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Approval;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\UsageData\Approval\ApprovalDetector;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Approval\ApprovalDetector
 */
class ApprovalDetectorTest extends TestCase
{
    public function testNeedsApprovalReturnsThePassedInParameter(): void
    {
        $approvalDetector = new ApprovalDetector(false, new StaticSystemConfigService());

        static::assertFalse($approvalDetector->needsApprovalRequest());

        $approvalDetector = new ApprovalDetector(true, new StaticSystemConfigService());

        static::assertTrue($approvalDetector->needsApprovalRequest());
    }

    public function testApprovalIsNotRequestedIfConfigValueIsNotSet(): void
    {
        $approvalDetector = new ApprovalDetector(true, new StaticSystemConfigService());

        static::assertFalse($approvalDetector->isApprovalAlreadyRequested());
    }

    public function testApprovalIsAlreadyRequestedIfConfigValueIsSet(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ApprovalDetector::SYSTEM_CONFIG_KEY_SHARE_DATA => true,
        ]);

        $approvalDetector = new ApprovalDetector(true, $systemConfig);

        static::assertTrue($approvalDetector->isApprovalAlreadyRequested());

        $systemConfig->set(ApprovalDetector::SYSTEM_CONFIG_KEY_SHARE_DATA, false);

        static::assertTrue($approvalDetector->isApprovalAlreadyRequested());
    }

    public function testIsApprovalGivenReturnsConfigValue(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ApprovalDetector::SYSTEM_CONFIG_KEY_SHARE_DATA => true,
        ]);

        $approvalDetector = new ApprovalDetector(true, $systemConfig);

        static::assertTrue($approvalDetector->isApprovalGiven());

        $systemConfig->set(ApprovalDetector::SYSTEM_CONFIG_KEY_SHARE_DATA, false);

        static::assertFalse($approvalDetector->isApprovalGiven());
    }

    public function testIsApprovalGivenIsFalseIfConfigValueIsNotSet(): void
    {
        $approvalDetector = new ApprovalDetector(true, new StaticSystemConfigService());

        static::assertFalse($approvalDetector->isApprovalGiven());
    }
}
