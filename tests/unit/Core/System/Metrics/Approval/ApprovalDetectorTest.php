<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Metrics\Approval;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Metrics\Approval\ApprovalDetector;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\Metrics\Approval\ApprovalDetector
 */
class ApprovalDetectorTest extends TestCase
{
    public function testDetectsThatApprovalIsNeeded(): void
    {
        $detector = new ApprovalDetector(true);
        static::assertTrue($detector->needsApprovalRequest());
    }

    public function testDetectsThatApprovalIsNotNeeded(): void
    {
        $detector = new ApprovalDetector(false);
        static::assertFalse($detector->needsApprovalRequest());
    }
}
