<?php
declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\ConfigLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider;

/**
 * @internal
 */
#[Package('storefront')]
class DatabaseAvailableThemeProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testReadSalesChannels(): void
    {
        $themeId = $this->getThemeId();

        $firstSc = $this->createSalesChannel();
        $secondSc = $this->createSalesChannel([
            'active' => false,
            'themes' => [
                [
                    'id' => $themeId,
                ],
            ],
        ]);

        $list = static::getContainer()->get(DatabaseAvailableThemeProvider::class)->load(Context::createDefaultContext(), false);

        static::assertArrayNotHasKey($firstSc['id'], $list, 'sc has no theme assigned');
        static::assertArrayHasKey($secondSc['id'], $list, 'sc has no theme assigned');
        static::assertSame($themeId, $list[$secondSc['id']]);
    }

    public function testItFiltersInactiveSalesChannels(): void
    {
        $themeId = $this->getThemeId();

        $inactive = $this->createSalesChannel([
            'active' => false,
            'themes' => [
                [
                    'id' => $themeId,
                ],
            ],
        ]);

        $list = static::getContainer()->get(DatabaseAvailableThemeProvider::class)->load(Context::createDefaultContext(), true);

        static::assertArrayNotHasKey($inactive['id'], $list, 'inactive sales channel was returned but shouldn\'t');
    }

    private function getThemeId(): string
    {
        $id = static::getContainer()->get('theme.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        static::assertIsString($id);

        return $id;
    }
}
