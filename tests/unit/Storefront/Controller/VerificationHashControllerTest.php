<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\VerificationHashController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(VerificationHashController::class)]
class VerificationHashControllerTest extends TestCase
{
    public function testGetVerificationHash(): void
    {
        $systemConfigMock = $this->createMock(SystemConfigService::class);
        $systemConfigMock->expects(static::once())->method('getString')->willReturn('TheVerificationHash123');

        $controller = new VerificationHashController($systemConfigMock);
        $response = $controller->load();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals('TheVerificationHash123', $response->getContent());
        static::assertEquals('text/plain', $response->headers->get('Content-Type'));
    }

    public function testGetVerificationHashEmpty(): void
    {
        $systemConfigMock = $this->createMock(SystemConfigService::class);
        $systemConfigMock->expects(static::once())->method('getString')->willReturn('');

        $controller = new VerificationHashController($systemConfigMock);
        $response = $controller->load();

        static::assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertEquals('', $response->getContent());
        static::assertEquals('text/plain', $response->headers->get('Content-Type'));
    }
}
