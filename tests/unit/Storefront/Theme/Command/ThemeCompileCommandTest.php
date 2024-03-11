<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Command\ThemeCompileCommand;
use Shopware\Storefront\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('storefront')]
#[CoversClass(ThemeCompileCommand::class)]
class ThemeCompileCommandTest extends TestCase
{
    #[DataProvider('getOptionsValue')]
    public function testItNegatesKeepAssetsOptionWhenPassed(bool $keepAssetsOption): void
    {
        $salesChannelId = 'sales-channel-id';
        $themeId = 'theme-id';

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects(static::once())
            ->method('compileTheme')
            ->with($salesChannelId, $themeId, static::anything(), null, !$keepAssetsOption);

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects(static::once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([$salesChannelId => $themeId]);

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider));

        $commandTester->execute(['--keep-assets' => $keepAssetsOption]);
        $commandTester->assertCommandIsSuccessful();
    }

    #[DataProvider('getOptionsValue')]
    public function testItPassesActiveOnlyFlagCorrectly(bool $activeOnly): void
    {
        $themeService = static::createMock(ThemeService::class);

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects(static::once())
            ->method('load')
            ->with(static::anything(), $activeOnly)
            ->willReturn([]);

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider));

        $commandTester->execute(['--active-only' => $activeOnly]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesSkipSalesChannelFlagCorrectly(): void
    {
        $salesChannelIdSkip1 = 'sales-channel-id1';
        $salesChannelIdSkip2 = 'sales-channel-id2';
        $salesChannelIdIncluded1 = 'sales-channel-id3';
        $salesChannelIdIncluded2 = 'sales-channel-id4';
        $themeId = 'theme-id';

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects(static::once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([
                $salesChannelIdSkip1 => $themeId,
                $salesChannelIdSkip2 => $themeId,
                $salesChannelIdIncluded1 => $themeId,
                $salesChannelIdIncluded2 => $themeId,
            ]);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects(static::exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(
                function (
                    string $actualSalesChannelId,
                    string $actualThemeId
                ) use (
                    $themeId,
                    $salesChannelIdIncluded1,
                    $salesChannelIdIncluded2
                ): void {
                    static::assertSame($themeId, $actualThemeId);
                    static::assertContains(
                        $actualSalesChannelId,
                        [$salesChannelIdIncluded1, $salesChannelIdIncluded2]
                    );
                }
            );

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider));

        $commandTester->execute(['--skip' => [$salesChannelIdSkip1, $salesChannelIdSkip2]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesOnlySalesChannelFlagCorrectly(): void
    {
        $salesChannelIdSkip1 = 'sales-channel-id1';
        $salesChannelIdSkip2 = 'sales-channel-id2';
        $salesChannelIdIncluded1 = 'sales-channel-id3';
        $salesChannelIdIncluded2 = 'sales-channel-id4';
        $themeId = 'theme-id';

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects(static::once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([
                $salesChannelIdSkip1 => $themeId,
                $salesChannelIdSkip2 => $themeId,
                $salesChannelIdIncluded1 => $themeId,
                $salesChannelIdIncluded2 => $themeId,
            ]);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects(static::exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(
                function (
                    string $actualSalesChannelId,
                    string $actualThemeId
                ) use (
                    $themeId,
                    $salesChannelIdIncluded1,
                    $salesChannelIdIncluded2
                ): void {
                    static::assertSame($themeId, $actualThemeId);
                    static::assertContains(
                        $actualSalesChannelId,
                        [$salesChannelIdIncluded1, $salesChannelIdIncluded2]
                    );
                }
            );

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider));

        $commandTester->execute(['--only' => [$salesChannelIdIncluded1, $salesChannelIdIncluded2]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesSkipThemeFlagCorrectly(): void
    {
        $salesChannelIdSkip1 = 'sales-channel-id1';
        $salesChannelIdSkip2 = 'sales-channel-id2';
        $salesChannelIdIncluded1 = 'sales-channel-id3';
        $salesChannelIdIncluded2 = 'sales-channel-id4';
        $themeIdSkip = 'theme-id-skip';
        $themeIdIncluded = 'theme-id-included';

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects(static::once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([
                $salesChannelIdSkip1 => $themeIdSkip,
                $salesChannelIdSkip2 => $themeIdSkip,
                $salesChannelIdIncluded1 => $themeIdIncluded,
                $salesChannelIdIncluded2 => $themeIdIncluded,
            ]);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects(static::exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(
                function (
                    string $actualSalesChannelId,
                    string $actualThemeId
                ) use (
                    $themeIdIncluded,
                    $salesChannelIdIncluded1,
                    $salesChannelIdIncluded2
                ): void {
                    static::assertSame($themeIdIncluded, $actualThemeId);
                    static::assertContains(
                        $actualSalesChannelId,
                        [$salesChannelIdIncluded1, $salesChannelIdIncluded2]
                    );
                }
            );

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider));

        $commandTester->execute(['--skip-themes' => [$themeIdSkip]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesOnlyThemeFlagCorrectly(): void
    {
        $salesChannelIdSkip1 = 'sales-channel-id1';
        $salesChannelIdSkip2 = 'sales-channel-id2';
        $salesChannelIdIncluded1 = 'sales-channel-id3';
        $salesChannelIdIncluded2 = 'sales-channel-id4';
        $themeIdSkip = 'theme-id-skip';
        $themeIdIncluded = 'theme-id-included';

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects(static::once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([
                $salesChannelIdSkip1 => $themeIdSkip,
                $salesChannelIdSkip2 => $themeIdSkip,
                $salesChannelIdIncluded1 => $themeIdIncluded,
                $salesChannelIdIncluded2 => $themeIdIncluded,
            ]);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects(static::exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(
                function (
                    string $actualSalesChannelId,
                    string $actualThemeId
                ) use (
                    $themeIdIncluded,
                    $salesChannelIdIncluded1,
                    $salesChannelIdIncluded2
                ): void {
                    static::assertSame($themeIdIncluded, $actualThemeId);
                    static::assertContains(
                        $actualSalesChannelId,
                        [$salesChannelIdIncluded1, $salesChannelIdIncluded2]
                    );
                }
            );

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider));

        $commandTester->execute(['--only-themes' => [$themeIdIncluded]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItFailsWithContradictingSalesChannelArgs(): void
    {
        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects(static::never())
            ->method('load');

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects(static::never())
            ->method('compileTheme');

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider));

        $salesChannelId = Uuid::randomHex();
        $commandTester->execute([
            '--only' => [$salesChannelId],
            '--skip' => [$salesChannelId],
        ]);
        static::assertSame(1, $commandTester->getStatusCode());
    }

    public function testItFailsWithContradictingThemeArgs(): void
    {
        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects(static::never())
            ->method('load');

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects(static::never())
            ->method('compileTheme');

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider));

        $themeId = Uuid::randomHex();
        $commandTester->execute([
            '--only-themes' => [$themeId],
            '--skip-themes' => [$themeId],
        ]);
        static::assertSame(1, $commandTester->getStatusCode());
    }

    /**
     * @return iterable<array<bool>>
     */
    public static function getOptionsValue(): iterable
    {
        yield [true];
        yield [false];
    }
}
