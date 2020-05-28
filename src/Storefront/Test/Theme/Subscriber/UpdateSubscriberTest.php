<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Subscriber\UpdateSubscriber;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;

class UpdateSubscriberTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    public function testCompilesAllThemes(): void
    {
        $themeService = $this->createMock(ThemeService::class);
        $themeLifecycleService = $this->createMock(ThemeLifecycleService::class);
        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $context = Context::createDefaultContext();
        $themes = $this->setupThemes($context);

        $updateSubscriber = new UpdateSubscriber($themeService, $themeLifecycleService, $salesChannelRepository);
        $event = new UpdatePostFinishEvent(Context::createDefaultContext(), 'v6.2.0', 'v6.2.1');

        $themeLifecycleService->expects(static::once())->method('refreshThemes');
        $themeService->expects(static::exactly(3))
            ->method('compileTheme')
            ->willReturnCallback(function ($salesChannelId, $themeId, $c) use (&$themes, $context) {
                $this->assertArrayHasKey($themeId, $themes);
                $this->assertSame($themes[$themeId], $salesChannelId);
                $this->assertEquals($context, $c);
                unset($themes[$themeId]);

                return true;
            });

        $updateSubscriber->updateFinished($event);
        static::assertEmpty($themes);
    }

    private function setupThemes(Context $context): array
    {
        /** @var EntityRepository $themeRepository */
        $themeRepository = $this->getContainer()->get('theme.repository');
        $themeSalesChannelRepository = $this->getContainer()->get('theme_sales_channel.repository');

        $criteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', 'Storefront'));
        $defaultThemeId = $themeRepository->searchIds($criteria, $context)->firstId();

        $otherThemeId = Uuid::randomHex();
        $defaultChildId = Uuid::randomHex();
        $themes = [
            $defaultThemeId => Defaults::SALES_CHANNEL,
            $otherThemeId => Uuid::randomHex(),
            $defaultChildId => Uuid::randomHex(),
        ];

        $this->createSalesChannel(['id' => $themes[$otherThemeId], 'domains' => [
            [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost/child',
            ],
        ]]);
        $this->createSalesChannel(['id' => $themes[$defaultChildId], 'domains' => [
            [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost/other',
            ],
        ]]);

        $themeRepository->clone($defaultThemeId, $context, $otherThemeId, ['technicalName' => 'testTheme']);
        $themeRepository->create([[
            'id' => $defaultChildId,
            'parentThemeId' => $defaultThemeId,
            'name' => 'Child theme',
            'author' => 'test',
            'active' => true,
        ]], $context);

        $themeSalesChannelRepository->create([
            [
                'themeId' => $defaultThemeId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
            ],
            [
                'themeId' => $otherThemeId,
                'salesChannelId' => $themes[$otherThemeId],
            ],
            [
                'themeId' => $defaultChildId,
                'salesChannelId' => $themes[$defaultChildId],
            ],
        ], $context);

        return $themes;
    }
}
