<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\LandingPage\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Group('store-api')]
#[Package('discovery')]
class LandingPageRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const LANGUAGE_IDS = [
        'en' => '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        'de' => '0f4ac850f69643cfb03d8d6ea5dc2647',
    ];

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->createData();
    }

    public function testGetLandingPage(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->ids->get('landing-page'), $response['id']);
        static::assertSame('My landing page', $response['name']);
        static::assertArrayHasKey('cmsPage', $response);
        static::assertSame($this->ids->get('cms-page'), $response['cmsPage']['id']);
    }

    public function testPostLandingPage(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->ids->get('landing-page'), $response['id']);
        static::assertArrayHasKey('cmsPage', $response);
    }

    public function testLoadLandingPageCmsSlotConfigFromParentLanguageOverride(): void
    {
        $this->createLanguages();

        $slotId = $this->ids->create('translated-slot');
        $landingPageId = $this->ids->create('translated-landing-page');

        /** @var EntityRepository<CmsPageCollection> $cmsPageRepository */
        $cmsPageRepository = static::getContainer()->get('cms_page.repository');
        /** @var EntityRepository<LandingPageCollection> $landingPageRepository */
        $landingPageRepository = static::getContainer()->get('landing_page.repository');

        $cmsPageId = $this->ids->create('translated-cms-page');
        $cmsPageRepository->create([[
            'id' => $cmsPageId,
            'name' => 'translated landing page',
            'type' => 'landingpage',
            'sections' => [[
                'id' => $this->ids->create('translated-section'),
                'type' => 'default',
                'position' => 0,
                'blocks' => [[
                    'type' => 'text',
                    'position' => 0,
                    'slots' => [[
                        'id' => $slotId,
                        'type' => 'text',
                        'slot' => 'content',
                        'config' => [
                            'content' => [
                                'source' => 'static',
                                'value' => 'layout placeholder',
                            ],
                        ],
                    ]],
                ]],
            ]],
        ]], Context::createDefaultContext());

        $landingPageRepository->create([[
            'id' => $landingPageId,
            'name' => 'Translated landing page',
            'url' => 'translated-landing-page',
            'active' => true,
            'cmsPageId' => $cmsPageId,
            'salesChannels' => [[
                'id' => $this->ids->get('sales-channel'),
            ]],
            'slotConfig' => [
                $slotId => [
                    'content' => [
                        'source' => 'static',
                        'value' => 'default language override',
                    ],
                ],
            ],
        ]], Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'languageId' => self::LANGUAGE_IDS['de'],
            'languages' => [
                ['id' => self::LANGUAGE_IDS['en']],
                ['id' => self::LANGUAGE_IDS['de']],
            ],
            'domains' => [[
                'languageId' => self::LANGUAGE_IDS['de'],
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost/de-test',
            ]],
        ]);

        $this->browser->request('GET', '/store-api/context');
        $contextResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextResponse['token'],
            $this->ids->get('sales-channel'),
            [SalesChannelContextService::LANGUAGE_ID => self::LANGUAGE_IDS['de']],
        );

        $response = static::getContainer()->get(LandingPageRoute::class)->load(
            $landingPageId,
            new Request(),
            $salesChannelContext,
        );

        $slot = $response->getLandingPage()
            ->getCmsPage()?->getSections()?->first()?->getBlocks()?->first()?->getSlots()?->first();

        static::assertSame(
            'default language override',
            $slot?->getConfig()['content']['value'] ?? null
        );
    }

    private function createData(): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository<CmsPageCollection> $cmsPageRepository */
        $cmsPageRepository = static::getContainer()->get('cms_page.repository');
        /** @var EntityRepository<LandingPageCollection> $landingPageRepository */
        $landingPageRepository = static::getContainer()->get('landing_page.repository');

        $cmsPageId = $this->ids->create('cms-page');
        $cmsPageRepository->create([
            [
                'id' => $cmsPageId,
                'name' => 'test page',
                'type' => 'product_list',
                'sections' => [
                    [
                        'id' => $this->ids->create('section'),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'product-listing',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'type' => 'product-listing',
                                        'slot' => 'content',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $context);

        $landingPageRepository->create([
            [
                'id' => $this->ids->create('landing-page'),
                'name' => 'My landing page',
                'url' => 'my-landing-page',
                'active' => true,
                'cmsPageId' => $cmsPageId,
                'salesChannels' => [
                    [
                        'id' => $this->ids->get('sales-channel'),
                    ],
                ],
            ],
        ], $context);
    }

    private function createLanguages(): void
    {
        static::getContainer()->get('language.repository')->upsert([
            [
                'id' => self::LANGUAGE_IDS['en'],
                'name' => 'English',
                'localeId' => $this->getLocaleId('en-GB'),
                'translationCodeId' => $this->getLocaleId('en-GB'),
            ],
            [
                'id' => self::LANGUAGE_IDS['de'],
                'name' => 'German',
                'localeId' => $this->getLocaleId('de-DE'),
                'translationCodeId' => $this->getLocaleId('de-DE'),
                'parentId' => self::LANGUAGE_IDS['en'],
            ],
        ], Context::createDefaultContext());
    }

    private function getLocaleId(string $code): string
    {
        $localeId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM locale WHERE code = :code',
            ['code' => $code],
        );

        static::assertIsString($localeId);
        static::assertTrue(Uuid::isValid($localeId));

        return $localeId;
    }
}
