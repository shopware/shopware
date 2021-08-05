<?php
declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\ConfigLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider;

class DatabaseAvailableThemeProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testReadSalesChannels(): void
    {
        $themeId = $this->getThemeId();

        $firstSc = $this->createSalesChannel();
        $secondSc = $this->createSalesChannel([
            'themes' => [
                [
                    'id' => $themeId,
                ],
            ],
        ]);

        $list = $this->getContainer()->get(DatabaseAvailableThemeProvider::class)->load(Context::createDefaultContext());

        static::assertArrayNotHasKey($firstSc['id'], $list, 'sc has no theme assigned');
        static::assertArrayHasKey($secondSc['id'], $list, 'sc has no theme assigned');
        static::assertSame($themeId, $list[$secondSc['id']]);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->getContainer()->get(DatabaseAvailableThemeProvider::class)->getDecorated();
    }

    private function getThemeId(): string
    {
        return $this->getContainer()->get('theme.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId();
    }
}
