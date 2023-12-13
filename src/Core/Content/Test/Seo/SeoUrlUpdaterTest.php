<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class SeoUrlUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    // Language codes
    private const DEFAULT = 'en-GB';
    private const PARENT = 'de-DE';
    private const CHILD = 'de-TEST';

    private TestDataCollection $ids;

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

        $this->ids = new TestDataCollection();

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

        // Create headless sales channel.
        $headlessSalesChannelOverride = $salesChannelOverride;
        $headlessSalesChannelOverride['typeId'] = Defaults::SALES_CHANNEL_TYPE_API;
        $headlessSalesChannelOverride['domains'][0]['url'] = 'http://localhost/headless';
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
        $this->getContainer()->get(Connection::class)->insert('seo_url_template', [
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

        $this->getContainer()->get('product.repository')->create([
            $productBuilder->build(),
        ], Context::createDefaultContext());

        // Manually trigger the updater, as the automatic updater triggers only for the storefront routes
        $this->getContainer()->get(SeoUrlUpdater::class)->update(
            TestProductSeoUrlRoute::ROUTE_NAME,
            [$this->ids->get('p1')]
        );

        // Search for created seo url of storefront sales channel.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $this->ids->get('p1')));
        $criteria->addFilter(new EqualsFilter('routeName', TestProductSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $this->storefrontSalesChannel['id']));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $this->getContainer()->get('seo_url.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->first();

        // Check if seo url was created
        static::assertNotNull($seoUrl);

        // Check if seo path matches the expected path
        static::assertStringStartsWith($pathInfo, $seoUrl->getSeoPathInfo());

        // Verify URL of headless sales channel.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('routeName', TestProductSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $this->headlessSalesChannel['id']));
        $seoUrl = $this->getContainer()->get('seo_url.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->first();

        // Check that no seo url was created.
        static::assertNull($seoUrl);
    }

    /**
     * @return list<array{translations: list<string>, pathInfo: non-empty-string}>
     */
    public static function seoLanguageDataProvider(): array
    {
        return [
            [
                // All translations available > expected to use child translation
                'translations' => [self::DEFAULT, self::PARENT, self::CHILD],
                'pathInfo' => self::CHILD,
            ],
            [
                // Parent translation missing > expected to use child translation
                'translations' => [self::DEFAULT, self::CHILD],
                'pathInfo' => self::CHILD,
            ],
            [
                // Child translation missing > expected to use parent translation
                'translations' => [self::DEFAULT, self::PARENT],
                'pathInfo' => self::PARENT,
            ],
            [
                // Parent and child translations missing > expected to use default translation
                'translations' => [self::DEFAULT],
                'pathInfo' => self::DEFAULT,
            ],
        ];
    }
}
