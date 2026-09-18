<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeSnippetTranslationTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testThemeSnippetsGetsMergedWithOverride(): void
    {
        if (!static::getContainer()->has(ThemeService::class) || !static::getContainer()->has('theme.repository')) {
            static::markTestSkipped('This test needs storefront to be installed.');
        }

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL
        );

        $translator = static::getContainer()->get(Translator::class);
        $themeService = static::getContainer()->get(ThemeService::class);
        $themeRepo = static::getContainer()->get('theme.repository');
        $loader = static::getContainer()->get(DatabaseSalesChannelThemeLoader::class);

        // Install the app
        $this->loadAppsFromDir(__DIR__ . '/Fixtures/theme');
        $this->reloadAppSnippets();

        // Ensure the default Storefront theme is active
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'Storefront'));
        $defaultThemeId = $themeRepo->searchIds($criteria, $salesChannelContext->getContext())->firstId();
        static::assertNotNull($defaultThemeId, 'Default theme not found');
        $themeService->assignTheme($defaultThemeId, $salesChannelContext->getSalesChannelId(), $salesChannelContext->getContext(), true);

        // Inject the sales channel and assert that the original snippet is used
        $translator->injectSettings(
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getLanguageId(),
            'en-GB',
            $salesChannelContext->getContext()
        );

        static::assertSame('Service date equivalent to invoice date', $translator->trans('document.serviceDateNotice'));
        $translator->reset();
        $loader->reset();

        // Assign the SwagTheme and assert that the snippet is overwritten
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
        $themeId = $themeRepo->searchIds($criteria, $salesChannelContext->getContext())->firstId();

        static::assertNotNull($themeId);

        $themeService->assignTheme($themeId, $salesChannelContext->getSalesChannelId(), $salesChannelContext->getContext(), true);

        $translator->injectSettings(
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getLanguageId(),
            'en-GB',
            $salesChannelContext->getContext()
        );

        static::assertSame('Swag Theme serviceDateNotice EN', $translator->trans('document.serviceDateNotice'));

        $translator->reset();
        $loader->reset();

        // In reset, we ignore all theme snippets and use the default ones
        static::assertSame('Service date equivalent to invoice date', $translator->trans('document.serviceDateNotice'));

        // Assign the Storefront theme again and assert that the original snippet is used again
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'Storefront'));
        $themeId = $themeRepo->searchIds($criteria, $salesChannelContext->getContext())->firstId();
        static::assertNotNull($themeId);

        $themeService->assignTheme($themeId, $salesChannelContext->getSalesChannelId(), $salesChannelContext->getContext(), true);

        $translator->reset();
        $loader->reset();

        $translator->injectSettings(
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getLanguageId(),
            'en-GB',
            $salesChannelContext->getContext()
        );

        static::assertSame('Service date equivalent to invoice date', $translator->trans('document.serviceDateNotice'));
    }
}
