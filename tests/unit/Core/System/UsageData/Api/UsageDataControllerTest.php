<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\UsageData\Api\UsageDataController;
use Shopware\Core\System\UsageData\Approval\ApprovalDetector;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Api\UsageDataController
 */
class UsageDataControllerTest extends TestCase
{
    public function testApprovalIsNotNeeded(): void
    {
        $detector = $this->createMock(ApprovalDetector::class);
        $detector->method('needsApprovalRequest')->willReturn(false);
        $controller = new UsageDataController($detector);

        static::assertFalse($this->getJsonResponseResult($controller->needsApprovalRequest()));
    }

    public function testApprovalIsNeededButWasAlreadyRequested(): void
    {
        $detector = $this->createMock(ApprovalDetector::class);
        $detector->method('needsApprovalRequest')->willReturn(true);
        $detector->method('isApprovalAlreadyRequested')->willReturn(true);
        $controller = new UsageDataController($detector);

        static::assertFalse($this->getJsonResponseResult($controller->needsApprovalRequest()));
    }

    public function testApprovalIsNeededAndHasNotBeenRequestedYet(): void
    {
        $detector = $this->createMock(ApprovalDetector::class);
        $detector->method('needsApprovalRequest')->willReturn(true);
        $detector->method('isApprovalAlreadyRequested')->willReturn(false);
        $controller = new UsageDataController($detector);

        static::assertTrue($this->getJsonResponseResult($controller->needsApprovalRequest()));
    }

    private function getJsonResponseResult(JsonResponse $response): bool
    {
        $json = $response->getContent();
        static::assertIsString($json);

        $result = json_decode($json);
        static::assertIsBool($result);

        return $result;
    }
}
