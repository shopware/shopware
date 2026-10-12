<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\ProductStoreApiUrlRoute;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class SeoUrlUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    // Language codes
    private const DEFAULT = 'en-GB';
    private const PARENT = 'de-DE';
    private const CHILD = 'de-DE-1';

    private IdsCollection $ids;

    /**
     * @var array<string, mixed>
     */
    private array $storefrontSalesChannel;

    /**
     * @var array<string, mixed>
     */
    private array $headlessSalesChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();

        // Get language ids
        $this->ids->set(self::DEFAULT, Defaults::LANGUAGE_SYSTEM);
        $this->ids->set(self::PARENT, $this->getDeDeLanguageId());
        $this->ids->create(self::CHILD);

        $salesChannelOverride = [
            // Create child language
            'language' => [
                'id' => $this->ids->get(self::CHILD),
                'name' => self::CHILD,
                'parentId' => $this->ids->get(self::PARENT),
                'active' => true,
                // Create locale for child language
                'locale' => [
                    'id' => $this->ids->create('childLocale'),
                    'code' => self::CHILD,
                    'translations' => [
                        [
                            'languageId' => $this->ids->get(self::DEFAULT),
                            'name' => self::CHILD,
                            'territory' => self::CHILD,
                        ],
                    ],
                ],
                'translationCodeId' => $this->ids->get('childLocale'),
            ],
            'languages' => [['id' => $this->ids->get(self::CHILD)]],
            // Add domain for child language
            'domains' => [
                [
                    'languageId' => $this->ids->get(self::CHILD),
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale(self::PARENT),
                ],
            ],
        ];

        // Create storefront saleschannel for child language
        $storefrontSalesChannelOverride = $salesChannelOverride;
        $storefrontSalesChannelOverride['typeId'] = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
        $storefrontSalesChannelOverride['domains'][0]['url'] = 'http://localhost/storefront';
        $this->storefrontSalesChannel = $this->createSalesChannel($storefrontSalesChannelOverride);

        // Create headless sales channel with an external storefront domain (SEO URLs are only generated for those).
        $headlessSalesChannelOverride = $salesChannelOverride;
        $headlessSalesChannelOverride['typeId'] = Defaults::SALES_CHANNEL_TYPE_API;
        $headlessSalesChannelOverride['domains'][0]['url'] = 'http://localhost/headless';
        $headlessSalesChannelOverride['domains'][0]['isExternalStorefront'] = true;
        $this->headlessSalesChannel = $this->createSalesChannel($headlessSalesChannelOverride);
    }

    /**
     * Checks whether the seo url updater is using the correct language for translations.
     *
     * @param list<string> $translations
     * @param non-empty-string $pathInfo
     */
    #[DataProvider('seoLanguageDataProvider')]
    public function testSeoLanguageInheritance(array $translations, string $pathInfo): void
    {
        static::getContainer()->get(Connection::class)->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'route_name' => TestProductSeoUrlRoute::ROUTE_NAME,
            'entity_name' => ProductDefinition::ENTITY_NAME,
            'template' => '{{ product.translated.name }}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $productBuilder = (new ProductBuilder($this->ids, 'p1'))
            ->price(100)
            ->name(self::DEFAULT);

        foreach ($translations as $translation) {
            $productBuilder->translation($this->ids->get($translation), 'name', $translation);
        }

        static::getContainer()->get('product.repository')->create([
            $productBuilder->build(),
        ], Context::createDefaultContext());

        // Manually trigger the updater, as the automatic updater triggers only for the storefront routes
        static::getContainer()->get(SeoUrlUpdater::class)->update(
            TestProductSeoUrlRoute::ROUTE_NAME,
            [$this->ids->get('p1')]
        );

        // Search for created seo url of storefront sales channel.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $this->ids->get('p1')));
        $criteria->addFilter(new EqualsFilter('routeName', TestProductSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $this->storefrontSalesChannel['id']));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = static::getContainer()->get('seo_url.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->getEntities()->first();

        // Check if seo url was created
        static::assertNotNull($seoUrl);

        // Check if seo path matches the expected path
        static::assertStringStartsWith($pathInfo, $seoUrl->getSeoPathInfo());

        // Verify URL of headless sales channel.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('routeName', TestProductSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $this->headlessSalesChannel['id']));
        $seoUrl = static::getContainer()->get('seo_url.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->getEntities()->first();

        // Check that no seo url was created.
        static::assertNull($seoUrl);
    }

    public function testHeadlessSalesChannelSeoUrlsAreGeneratedForVisibleProductsOnly(): void
    {
        // store-api template configured for the headless sales channel
        static::getContainer()->get(Connection::class)->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::fromHexToBytes($this->headlessSalesChannel['id']),
            'route_name' => ProductStoreApiUrlRoute::ROUTE_NAME,
            'entity_name' => ProductDefinition::ENTITY_NAME,
            'template' => '{{ product.translated.name }}',
            'is_headless' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $visible = (new ProductBuilder($this->ids, 'visible'))
            ->price(100)
            ->name('visible-product')
            ->visibility($this->headlessSalesChannel['id'])
            ->build();

        // not assigned to the headless sales channel, so it must not get a SEO URL there
        $hidden = (new ProductBuilder($this->ids, 'hidden'))
            ->price(100)
            ->name('hidden-product')
            ->build();

        static::getContainer()->get('product.repository')->create([$visible, $hidden], Context::createDefaultContext());

        static::getContainer()->get(SeoUrlUpdater::class)->update(
            ProductStoreApiUrlRoute::ROUTE_NAME,
            [$this->ids->get('visible'), $this->ids->get('hidden')]
        );

        $seoUrl = $this->findHeadlessProductSeoUrl($this->ids->get('visible'));
        static::assertNotNull($seoUrl);
        static::assertSame($this->headlessSalesChannel['id'], $seoUrl->getSalesChannelId());
        static::assertSame(ProductStoreApiUrlRoute::ROUTE_NAME, $seoUrl->getRouteName());
        static::assertSame('visible-product', $seoUrl->getSeoPathInfo());

        static::assertNull($this->findHeadlessProductSeoUrl($this->ids->get('hidden')));
    }

    public function testAnUnrenderableTemplateKeepsTheExistingSeoUrl(): void
    {
        $templateId = Uuid::randomBytes();
        $connection = static::getContainer()->get(Connection::class);
        $connection->insert('seo_url_template', [
            'id' => $templateId,
            'sales_channel_id' => Uuid::fromHexToBytes($this->headlessSalesChannel['id']),
            'route_name' => ProductStoreApiUrlRoute::ROUTE_NAME,
            'entity_name' => ProductDefinition::ENTITY_NAME,
            'template' => '{{ product.translated.name }}',
            'is_headless' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $product = (new ProductBuilder($this->ids, 'product'))
            ->price(100)
            ->name('generated-product')
            ->visibility($this->headlessSalesChannel['id'])
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $updater = static::getContainer()->get(SeoUrlUpdater::class);
        $updater->update(ProductStoreApiUrlRoute::ROUTE_NAME, [$this->ids->get('product')]);

        $seoUrl = $this->findHeadlessProductSeoUrl($this->ids->get('product'));
        static::assertNotNull($seoUrl);
        static::assertSame('generated-product', $seoUrl->getSeoPathInfo());

        // A template referencing a field that does not exist cannot be rendered. The
        // regeneration must leave the existing URL alone instead of flagging it deleted,
        // which would make the storefront answer 404 for it.
        $connection->update(
            'seo_url_template',
            ['template' => '{{ product.translated.customFields.doesNotExist }}'],
            ['id' => $templateId]
        );

        $updater->update(ProductStoreApiUrlRoute::ROUTE_NAME, [$this->ids->get('product')]);

        $seoUrl = $this->findHeadlessProductSeoUrl($this->ids->get('product'));
        static::assertNotNull($seoUrl);
        static::assertSame('generated-product', $seoUrl->getSeoPathInfo());
        static::assertFalse($seoUrl->getIsDeleted());
        static::assertTrue($seoUrl->getIsCanonical());
    }

    public function testHeadlessSalesChannelWithoutExternalStorefrontDomainGeneratesNoSeoUrls(): void
    {
        // flag the domain as a non-external storefront: no SEO URLs must be generated for it
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE `sales_channel_domain` SET `is_external_storefront` = 0 WHERE `sales_channel_id` = :id',
            ['id' => Uuid::fromHexToBytes($this->headlessSalesChannel['id'])]
        );

        static::getContainer()->get(Connection::class)->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::fromHexToBytes($this->headlessSalesChannel['id']),
            'route_name' => ProductStoreApiUrlRoute::ROUTE_NAME,
            'entity_name' => ProductDefinition::ENTITY_NAME,
            'template' => '{{ product.translated.name }}',
            'is_headless' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $product = (new ProductBuilder($this->ids, 'no-external'))
            ->price(100)
            ->name('no-external-product')
            ->visibility($this->headlessSalesChannel['id'])
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        static::getContainer()->get(SeoUrlUpdater::class)->update(
            ProductStoreApiUrlRoute::ROUTE_NAME,
            [$this->ids->get('no-external')]
        );

        static::assertNull($this->findHeadlessProductSeoUrl($this->ids->get('no-external')));
    }

    public function testHeadlessSalesChannelInheritsDefaultTemplate(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        // Reset the store-api templates (incl. the seeded default) so the test controls the values.
        $connection->executeStatement(
            'DELETE FROM `seo_url_template` WHERE `route_name` = :route',
            ['route' => ProductStoreApiUrlRoute::ROUTE_NAME]
        );

        // "all sales channels" default template (sales_channel_id IS NULL) for the store-api route ...
        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => null,
            'route_name' => ProductStoreApiUrlRoute::ROUTE_NAME,
            'entity_name' => ProductDefinition::ENTITY_NAME,
            'template' => '{{ product.translated.name }}',
            'is_headless' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // ... a headless channel row without an own template (NULL) inherits that default.
        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::fromHexToBytes($this->headlessSalesChannel['id']),
            'route_name' => ProductStoreApiUrlRoute::ROUTE_NAME,
            'entity_name' => ProductDefinition::ENTITY_NAME,
            'template' => null,
            'is_headless' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $product = (new ProductBuilder($this->ids, 'headless-inherit'))
            ->price(100)
            ->name('inherited-product')
            ->visibility($this->headlessSalesChannel['id'])
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        static::getContainer()->get(SeoUrlUpdater::class)->update(
            ProductStoreApiUrlRoute::ROUTE_NAME,
            [$this->ids->get('headless-inherit')]
        );

        $seoUrl = $this->findHeadlessProductSeoUrl($this->ids->get('headless-inherit'));
        static::assertNotNull($seoUrl);
        static::assertSame('inherited-product', $seoUrl->getSeoPathInfo());
    }

    /**
     * @return iterable<string, array{translations: list<string>, pathInfo: non-empty-string}>
     */
    public static function seoLanguageDataProvider(): iterable
    {
        yield 'child path info is used when all translations are available' => [
            'translations' => [self::DEFAULT, self::PARENT, self::CHILD],
            'pathInfo' => self::CHILD,
        ];
        yield 'child path info is used when parent translation is missing' => [
            'translations' => [self::DEFAULT, self::CHILD],
            'pathInfo' => self::CHILD,
        ];
        yield 'parent path info is used when child translation is missing' => [
            'translations' => [self::DEFAULT, self::PARENT],
            'pathInfo' => self::PARENT,
        ];
        yield 'default path info is used when parent and child translations are missing' => [
            'translations' => [self::DEFAULT],
            'pathInfo' => self::DEFAULT,
        ];
    }

    private function findHeadlessProductSeoUrl(string $foreignKey): ?SeoUrlEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $foreignKey));
        $criteria->addFilter(new EqualsFilter('routeName', ProductStoreApiUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $this->headlessSalesChannel['id']));

        $seoUrl = static::getContainer()->get('seo_url.repository')->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $seoUrl instanceof SeoUrlEntity ? $seoUrl : null;
    }
}
