<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\AbstractScssCompiler;
use Shopware\Storefront\Theme\Controller\ThemeController;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeController::class)]
class ThemeControllerTest extends TestCase
{
    public function testUpdateThemeWithResetCommitsResetAndValuesOfTheInjectedServiceBeforeCompiling(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $config = ['sw-color-brand-primary' => ['value' => '#123456']];

        $calls = new \ArrayObject();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('transactional')->willReturnCallback(
            static function (\Closure $closure) use ($calls): void {
                $calls->append('begin');
                $closure();
                $calls->append('commit');
            }
        );

        // A decorator of the theme service must see both calls, like before the transaction.
        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->once())->method('resetTheme')->with($themeId, $context)
            ->willReturnCallback(static function () use ($calls): void {
                $calls->append('reset');
            });
        $themeService->expects($this->once())->method('updateTheme')->with($themeId, $config, '', $context)
            ->willReturnCallback(static function () use ($calls, $context): void {
                $calls->append($context->hasState(ThemeService::STATE_SKIP_COMPILE) ? 'update without compile' : 'update');
            });
        $themeService->expects($this->once())->method('compileThemeById')->with($themeId, $context, null, false)
            ->willReturnCallback(static function () use ($calls): array {
                $calls->append('compile');

                return [];
            });

        $this->createController($themeService, $connection)
            ->updateTheme($themeId, new Request(['reset' => '1'], ['config' => $config]), $context);

        static::assertSame(['begin', 'reset', 'update without compile', 'commit', 'compile'], $calls->getArrayCopy());
        static::assertFalse($context->hasState(ThemeService::STATE_SKIP_COMPILE));
    }

    public function testFailedUpdateThemeWithResetDropsTheRuntimeConfigCachedByTheReset(): void
    {
        $failure = new \RuntimeException('value write failed');
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willThrowException($failure);
        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->never())->method('compileThemeById');
        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService->expects($this->once())->method('resetCaches');

        $this->expectExceptionObject($failure);

        $this->createController($themeService, $connection, $runtimeConfigService)
            ->updateTheme(Uuid::randomHex(), new Request(['reset' => '1']), Context::createDefaultContext());
    }

    public function testUpdateThemeWithoutResetMergesTheConfig(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $config = ['sw-color-brand-primary' => ['value' => '#123456']];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('transactional');
        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->never())->method('resetTheme');
        $themeService->expects($this->once())->method('updateTheme')->with($themeId, $config, '', $context)
            ->willReturnCallback(static function () use ($context): void {
                static::assertFalse($context->hasState(ThemeService::STATE_SKIP_COMPILE));
            });

        $this->createController($themeService, $connection)
            ->updateTheme($themeId, new Request([], ['config' => $config]), $context);
    }

    private function createController(
        ThemeService $themeService,
        Connection $connection,
        ?ThemeRuntimeConfigService $runtimeConfigService = null,
    ): ThemeController {
        return new ThemeController(
            $themeService,
            static::createStub(AbstractScssCompiler::class),
            $connection,
            $runtimeConfigService ?? static::createStub(ThemeRuntimeConfigService::class),
        );
    }
}
