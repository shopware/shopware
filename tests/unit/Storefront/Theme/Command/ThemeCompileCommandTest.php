<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Command\ThemeCompileCommand;
use Shopware\Storefront\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\Command\ThemeCompileCommand
 */
#[Package('storefront')]
class ThemeCompileCommandTest extends TestCase
{
    /**
     * @dataProvider getOptionsValue
     */
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

    /**
     * @dataProvider getOptionsValue
     */
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

    /**
     * @return iterable<array<bool>>
     */
    public static function getOptionsValue(): iterable
    {
        yield [true];
        yield [false];
    }
}
