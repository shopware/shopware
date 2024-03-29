<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\SeedingThemePathBuilder;

/**
 * @internal
 */
#[CoversClass(SeedingThemePathBuilder::class)]
class SeedingThemePathBuilderTest extends TestCase
{
    public function testAssemblePathDoesNotChangeWithoutChangedSeed(): void
    {
        $pathBuilder = new SeedingThemePathBuilder(new StaticSystemConfigService());

        $path = $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme');

        static::assertEquals($path, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme'));
    }

    public function testAssembledPathAfterSavingIsTheSameAsPreviouslyGenerated(): void
    {
        $pathBuilder = new SeedingThemePathBuilder(new StaticSystemConfigService());

        $generatedPath = $pathBuilder->generateNewPath(TestDefaults::SALES_CHANNEL, 'theme', 'foo');

        // assert seeding is taking into account when generating a new path
        static::assertNotEquals($generatedPath, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme'));

        $pathBuilder->saveSeed(TestDefaults::SALES_CHANNEL, 'theme', 'foo');

        // assert that the path is the same after saving
        static::assertEquals($generatedPath, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, 'theme'));
    }
}
