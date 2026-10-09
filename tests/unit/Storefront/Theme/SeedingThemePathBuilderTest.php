<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\SeedingThemePathBuilder;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SeedingThemePathBuilder::class)]
class SeedingThemePathBuilderTest extends TestCase
{
    public function testAssemblePathDoesNotChangeWithoutChangedSeed(): void
    {
        $pathBuilder = new SeedingThemePathBuilder(new StaticSystemConfigService());

        $path = $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme');

        static::assertSame($path, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme'));
    }

    public function testAssembledPathAfterSavingIsTheSameAsPreviouslyGenerated(): void
    {
        $pathBuilder = new SeedingThemePathBuilder(new StaticSystemConfigService());

        $generatedPath = $pathBuilder->generateNewPath(TestDefaults::SALES_CHANNEL, 'theme', 'foo');

        // assert seeding is taking into account when generating a new path
        static::assertNotSame($generatedPath, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme'));

        $pathBuilder->saveSeed(TestDefaults::SALES_CHANNEL, 'theme', 'foo');

        // assert that the path is the same after saving
        static::assertSame($generatedPath, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme'));
    }

    public function testSavingSeedForOneThemeDoesNotChangeAnotherThemesPath(): void
    {
        $pathBuilder = new SeedingThemePathBuilder(new StaticSystemConfigService());

        // theme-a's path under the current seed
        $pathA = $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-a');

        // compiling theme-b saves a new seed for theme-b only
        $pathBuilder->saveSeed(TestDefaults::SALES_CHANNEL, 'theme-b', 'seed-b');

        // theme-b resolves to its new seeded path ...
        static::assertSame(
            $pathBuilder->generateNewPath(TestDefaults::SALES_CHANNEL, 'theme-b', 'seed-b'),
            $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-b'),
        );

        // ... while theme-a's path is unchanged (the seed is per theme, not shared per sales channel)
        static::assertSame($pathA, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-a'));
    }

    public function testSavingSeedForOneThemeDoesNotEraseAConcurrentlySavedSeed(): void
    {
        // Each seed is stored under its own key, so a save never has to read-modify-write a shared
        // map. Two overlapping compilations that persist their seed cannot overwrite each other.
        $pathBuilder = new SeedingThemePathBuilder(new StaticSystemConfigService());

        $pathBuilder->saveSeed(TestDefaults::SALES_CHANNEL, 'theme-b', 'seed-b');
        $pathBuilder->saveSeed(TestDefaults::SALES_CHANNEL, 'theme-c', 'seed-c');

        static::assertSame(
            $pathBuilder->generateNewPath(TestDefaults::SALES_CHANNEL, 'theme-b', 'seed-b'),
            $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-b'),
        );
        static::assertSame(
            $pathBuilder->generateNewPath(TestDefaults::SALES_CHANNEL, 'theme-c', 'seed-c'),
            $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-c'),
        );
    }

    public function testLegacySharedSeedAppliesToThemesUntilTheyAreRecompiled(): void
    {
        // a pre-existing sales-channel-wide (string) seed, as written by older versions
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => ['storefront.themeSeed' => 'legacy'],
        ]);
        $pathBuilder = new SeedingThemePathBuilder($configService);

        // every theme resolves via the shared legacy seed
        static::assertSame(
            $pathBuilder->generateNewPath(TestDefaults::SALES_CHANNEL, 'theme-a', 'legacy'),
            $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-a'),
        );

        // recompiling theme-b stores a per-theme seed, but the legacy seed stays as fallback
        $pathBuilder->saveSeed(TestDefaults::SALES_CHANNEL, 'theme-b', 'seed-b');

        static::assertSame(
            $pathBuilder->generateNewPath(TestDefaults::SALES_CHANNEL, 'theme-b', 'seed-b'),
            $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-b'),
        );
        // theme-a, not yet recompiled, still resolves via the retained legacy seed
        static::assertSame(
            $pathBuilder->generateNewPath(TestDefaults::SALES_CHANNEL, 'theme-a', 'legacy'),
            $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme-a'),
        );
    }
}
