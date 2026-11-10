<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
    public function testUpdateThemeWithResetLetsTheServiceResetInTheValueTransaction(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $config = ['sw-color-brand-primary' => ['value' => '#123456']];

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->never())->method('resetTheme');
        $themeService->expects($this->once())->method('updateTheme')->with($themeId, $config, '', $context)
            ->willReturnCallback(static function () use ($context): void {
                static::assertTrue($context->hasState(ThemeService::STATE_RESET_CONFIG));
            });

        $controller = new ThemeController($themeService, static::createStub(AbstractScssCompiler::class));
        $controller->updateTheme($themeId, new Request(['reset' => '1'], ['config' => $config]), $context);
    }

    public function testUpdateThemeWithoutResetMergesTheConfig(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $config = ['sw-color-brand-primary' => ['value' => '#123456']];

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->never())->method('resetTheme');
        $themeService->expects($this->once())->method('updateTheme')->with($themeId, $config, '', $context)
            ->willReturnCallback(static function () use ($context): void {
                static::assertFalse($context->hasState(ThemeService::STATE_RESET_CONFIG));
            });

        $controller = new ThemeController($themeService, static::createStub(AbstractScssCompiler::class));
        $controller->updateTheme($themeId, new Request([], ['config' => $config]), $context);
    }
}
