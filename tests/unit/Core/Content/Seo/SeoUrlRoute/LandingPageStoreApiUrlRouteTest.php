<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\LandingPageStoreApiUrlRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageStoreApiUrlRoute::class)]
class LandingPageStoreApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new LandingPageDefinition();
        $config = (new LandingPageStoreApiUrlRoute($definition))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(LandingPageStoreApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('store-api.landing-page.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['landingPageId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testPrepareCriteriaScopesToSalesChannel(): void
    {
        $criteria = new Criteria();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');

        (new LandingPageStoreApiUrlRoute(new LandingPageDefinition()))->prepareCriteria($criteria, $salesChannel);

        static::assertEquals(
            [
                new EqualsFilter('active', true),
                new EqualsFilter('salesChannels.id', 'sales-channel-id'),
            ],
            $criteria->getFilters()
        );
    }
}
