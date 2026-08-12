<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\AbstractScssCompiler;
use Shopware\Storefront\Theme\Controller\ThemeController;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeController::class)]
class ThemeControllerTest extends TestCase
{
    private ThemeService&MockObject $themeService;

    private ThemeController $controller;

    protected function setUp(): void
    {
        $this->themeService = $this->createMock(ThemeService::class);
        $this->controller = new ThemeController(
            $this->themeService,
            static::createStub(AbstractScssCompiler::class),
        );
    }

    public function testAssignThemeCompilesSynchronouslyWhenNoQueueIsRequested(): void
    {
        $context = Context::createDefaultContext();

        $this->themeService
            ->expects($this->once())
            ->method('assignTheme')
            ->with('theme-id', 'sales-channel-id', $context);

        $this->controller->assignTheme(
            'theme-id',
            'sales-channel-id',
            $context,
            new Request(['no-queue' => 'true']),
        );

        static::assertTrue($context->hasState(ThemeService::STATE_NO_QUEUE));
    }

    public function testAssignThemeKeepsAsynchronousCompilationByDefault(): void
    {
        $context = Context::createDefaultContext();

        $this->themeService
            ->expects($this->once())
            ->method('assignTheme')
            ->with('theme-id', 'sales-channel-id', $context);

        $this->controller->assignTheme('theme-id', 'sales-channel-id', $context, new Request());

        static::assertFalse($context->hasState(ThemeService::STATE_NO_QUEUE));
    }
}
