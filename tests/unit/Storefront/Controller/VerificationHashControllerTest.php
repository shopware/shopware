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

        static::assertEquals(
            new Response(
                'TheVerificationHash123',
                Response::HTTP_OK,
                ['Content-Type' => 'text/plain']
            ),
            $response
        );
    }

    public function testGetVerificationHashEmpty(): void
    {
        $systemConfigMock = $this->createMock(SystemConfigService::class);
        $systemConfigMock->expects(static::once())->method('getString')->willReturn('');

        $controller = new VerificationHashController($systemConfigMock);
        $response = $controller->load();

        static::assertEquals(
            new Response(
                '',
                Response::HTTP_NOT_FOUND,
                ['Content-Type' => 'text/plain']
            ),
            $response
        );
    }
}
