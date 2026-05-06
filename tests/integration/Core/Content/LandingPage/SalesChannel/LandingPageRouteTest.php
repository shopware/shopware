<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\LandingPage\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageException;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser as KernelBrowserAlias;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Group('store-api')]
class LandingPageRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const LANGUAGE_IDS = [
        'en' => '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        'de' => '0f4ac850f69643cfb03d8d6ea5dc2647',
    ];

    private KernelBrowserAlias $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('cache_rework', $this);
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->createData();
    }

    public function testWithDifferentSalesChannel(): void
    {
        $this->createSalesChannel([
            'id' => $this->ids->create('other-sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://testing',
                ],
            ],
        ]);

        $this->createData([
            'id' => $this->ids->create('new-landing-page'),
            'salesChannels' => [
                [
                    'id' => $this->ids->get('other-sales-channel'),
                ],
            ],
        ]);

        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('new-landing-page')
        );

        $this->assertError($this->ids->get('new-landing-page'));
    }

    public function testCmsPageResolved(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );
        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals($this->ids->get('landing-page'), $response['id']);
        static::assertIsArray($response['cmsPage']);

        static::assertEquals($this->ids->get('cms-page'), $response['cmsPage']['id']);
        static::assertCount(1, $response['cmsPage']['sections']);

        static::assertCount(1, $response['cmsPage']['sections'][0]['blocks']);

        $block = $response['cmsPage']['sections'][0]['blocks'][0];

        static::assertEquals('product-listing', $block['type']);

        static::assertCount(1, $block['slots']);

        $slot = $block['slots'][0];
        static::assertEquals('product-listing', $slot['type']);

        static::assertArrayHasKey('listing', $slot['data']);

        $listing = $slot['data']['listing'];

        static::assertArrayHasKey('aggregations', $listing);
        static::assertArrayHasKey('elements', $listing);
    }

    public function testIncludesConsidered(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('landing-page'),
            [
                'includes' => [
                    'product_manufacturer' => ['id', 'name', 'options'],
                    'product' => ['id', 'name', 'manufacturer', 'tax'],
                    'product_listing' => ['aggregations', 'elements'],
                    'tax' => ['id', 'name'],
                ],
            ]
        );
        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $listing = $response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing'];

        static::assertArrayNotHasKey('sortings', $listing);
        static::assertArrayNotHasKey('page', $listing);
        static::assertArrayNotHasKey('limit', $listing);

        static::assertArrayHasKey('manufacturer', $listing['aggregations']);
        $manufacturers = $listing['aggregations']['manufacturer'];

        foreach ($manufacturers['entities'] as $manufacturer) {
            static::assertEquals(['name', 'id', 'apiAlias'], array_keys($manufacturer));
        }

        $products = $listing['elements'];
        foreach ($products as $product) {
            static::assertEquals(['name', 'tax', 'manufacturer', 'id', 'apiAlias'], array_keys($product));
            static::assertEquals(['name', 'id', 'apiAlias'], array_keys($product['tax']));
        }
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

    private function assertError(string $landingPageId): void
    {
        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $error = LandingPageException::notFound($landingPageId);
        $expectedError = [
            'status' => (string) $error->getStatusCode(),
            'message' => $error->getMessage(),
        ];

        static::assertSame($expectedError['status'], $response['errors'][0]['status']);
        static::assertSame($expectedError['message'], $response['errors'][0]['detail']);
    }

    /**
     * @param array<string, mixed> $override
     */
    private function createData(array $override = []): void
    {
        $data = [
            'id' => $this->ids->create('landing-page'),
            'name' => 'Test',
            'url' => 'myUrl',
            'active' => true,
            'salesChannels' => [
                [
                    'id' => $this->ids->get('sales-channel'),
                ],
            ],
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
                'type' => 'product_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'product-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'product-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $data = array_merge($data, $override);

        static::getContainer()->get('landing_page.repository')
            ->create([$data], Context::createDefaultContext());
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
        $localeId = static::getContainer()->get(\Doctrine\DBAL\Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM locale WHERE code = :code',
            ['code' => $code],
        );

        static::assertIsString($localeId);
        static::assertTrue(Uuid::isValid($localeId));

        return $localeId;
    }
}
