<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\SeedingThemePathBuilder;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\SeedingThemePathBuilder
 */
class SeedingThemePathBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $themeId;

    public function setUp(): void
    {
        $this->themeId = $this->assignDefaultThemeToDefaultSalesChannel();
    }

    public function testAssemblePathDoesNotChangeWithoutChangedSeed(): void
    {
        $pathBuilder = $this->getContainer()->get(SeedingThemePathBuilder::class);

        $path = $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, $this->themeId);

        static::assertEquals($path, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, $this->themeId));
    }

    public function testAssembledPathAfterSavingIsTheSameAsPreviouslyGenerated(): void
    {
        $pathBuilder = $this->getContainer()->get(SeedingThemePathBuilder::class);

        $generatedPath = $pathBuilder->generateNewPath(TestDefaults::SALES_CHANNEL, $this->themeId, 'foo');

        // assert seeding is taking into account when generating a new path
        static::assertNotEquals($generatedPath, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, $this->themeId));

        $pathBuilder->saveSeed(TestDefaults::SALES_CHANNEL, $this->themeId, 'foo');

        // assert that the path is the same after saving
        static::assertEquals($generatedPath, $pathBuilder->assemblePath(TestDefaults::SALES_CHANNEL, $this->themeId));
    }

    private function assignDefaultThemeToDefaultSalesChannel(): string
    {
        $connection = $this->getContainer()->get(Connection::class);

        $themeId = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM theme WHERE technical_name = :technicalName', ['technicalName' => 'Storefront']);

        $connection->insert(
            'theme_sales_channel',
            [
                'theme_id' => Uuid::fromHexToBytes($themeId),
                'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            ]
        );

        return $themeId;
    }
}
