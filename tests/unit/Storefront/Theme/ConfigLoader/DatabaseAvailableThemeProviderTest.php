<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\ConfigLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider
 */
#[Package('storefront')]
class DatabaseAvailableThemeProviderTest extends TestCase
{
    public function testThemeProviderThrowsOnGetDecorated(): void
    {
        $themeProvider = new DatabaseAvailableThemeProvider(new StaticEntityRepository([]));

        static::expectException(DecorationPatternException::class);
        $themeProvider->getDecorated();
    }

    public function testLoadThrowsExceptionInNextMajorWhenCalledWithOnlyOneParameter(): void
    {
        $themeProvider = new DatabaseAvailableThemeProvider(new StaticEntityRepository([new SalesChannelCollection()]));

        static::expectException(\RuntimeException::class);
        $themeProvider->load(Context::createDefaultContext());
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testLoadCanStillBeCalledWithOneParameter(): void
    {
        $themeProvider = new DatabaseAvailableThemeProvider(new StaticEntityRepository([new SalesChannelCollection()]));

        static::assertEquals([], $themeProvider->load(Context::createDefaultContext()));
    }

    public function testThemeProviderIsSearchingForStorefrontsWithThemes(): void
    {
        $salesChannelRepository = new StaticEntityRepository([
            static function (Criteria $criteria, Context $context): EntitySearchResult {
                static::assertTrue($criteria->hasAssociation('themes'));

                static::assertEquals([
                    new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                ], $criteria->getFilters());

                return new EntitySearchResult(
                    SalesChannelDefinition::ENTITY_NAME,
                    0,
                    new SalesChannelCollection(),
                    null,
                    $criteria,
                    $context,
                );
            },
        ]);

        $themeProvider = new DatabaseAvailableThemeProvider($salesChannelRepository);

        static::assertEquals([], $themeProvider->load(Context::createDefaultContext(), false));
    }

    public function testThemeProviderIsSearchingForActiveStorefrontsWithThemes(): void
    {
        $salesChannelRepository = new StaticEntityRepository([
            static function (Criteria $criteria, Context $context): EntitySearchResult {
                static::assertTrue($criteria->hasAssociation('themes'));

                static::assertEquals([
                    new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                    new EqualsFilter('active', 1),
                ], $criteria->getFilters());

                return new EntitySearchResult(
                    SalesChannelDefinition::ENTITY_NAME,
                    0,
                    new SalesChannelCollection(),
                    null,
                    $criteria,
                    $context,
                );
            },
        ]);

        $themeProvider = new DatabaseAvailableThemeProvider($salesChannelRepository);

        static::assertEquals([], $themeProvider->load(Context::createDefaultContext(), true));
    }

    public function testThemeProviderReturnsIdsOfFoundSalesChannelsWithThemes(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-with-theme');
        $salesChannel->setUniqueIdentifier('sales-channel-with-theme');

        $theme = new ThemeEntity();
        $theme->setId('associated-theme');
        $theme->setUniqueIdentifier('associated-theme');

        $salesChannel->addExtension('themes', new ThemeCollection([$theme]));

        $themeProvider = new DatabaseAvailableThemeProvider(new StaticEntityRepository([
            new SalesChannelCollection([$salesChannel]),
        ]));

        static::assertEquals(
            ['sales-channel-with-theme' => 'associated-theme'],
            $themeProvider->load(Context::createDefaultContext(), false),
        );
    }

    public function testThemeProviderFiltersSalesChannelWithoutThemeAssignments(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-without-theme-association');
        $salesChannel->setUniqueIdentifier('sales-channel-without-theme-association');
        $salesChannel->addExtension('themes', new ThemeCollection());

        $themeProvider = new DatabaseAvailableThemeProvider(new StaticEntityRepository([
            new SalesChannelCollection([$salesChannel]),
        ]));

        static::assertEquals(
            [],
            $themeProvider->load(Context::createDefaultContext(), false),
        );
    }

    public function testThemeProviderFiltersSalesChannelsWithoutThemeAssociation(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-without-theme-extension');

        $themeProvider = new DatabaseAvailableThemeProvider(new StaticEntityRepository([
            new SalesChannelCollection([$salesChannel]),
        ]));

        static::assertEquals(
            [],
            $themeProvider->load(Context::createDefaultContext(), false),
        );
    }
}
