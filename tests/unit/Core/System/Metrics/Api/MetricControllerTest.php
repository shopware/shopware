<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Metrics\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Metrics\Api\MetricController;
use Shopware\Core\System\Metrics\Approval\ApprovalDetector;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\Metrics\Api\MetricController
 */
class MetricControllerTest extends TestCase
{
    public function testApprovalIsNotNeeded(): void
    {
        $detector = $this->createMock(ApprovalDetector::class);
        $detector->method('needsApprovalRequest')->willReturn(false);
        $configService = $this->createMock(SystemConfigService::class);
        $controller = new MetricController($detector, $configService);

        static::assertFalse($this->getJsonResponseResult($controller->needsApprovalRequest()));
    }

    public function testApprovalIsNeededButWasAlreadyRequested(): void
    {
        $detector = $this->createMock(ApprovalDetector::class);
        $detector->method('needsApprovalRequest')->willReturn(true);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->willReturn(true);
        $controller = new MetricController($detector, $configService);

        static::assertFalse($this->getJsonResponseResult($controller->needsApprovalRequest()));
    }

    public function testApprovalIsNeededAndHasNotBeenRequestedYet(): void
    {
        $detector = $this->createMock(ApprovalDetector::class);
        $detector->method('needsApprovalRequest')->willReturn(true);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->willReturn(null);
        $controller = new MetricController($detector, $configService);

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
