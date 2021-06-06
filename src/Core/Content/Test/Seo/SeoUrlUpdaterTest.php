<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Seo;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;

class SeoUrlUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    // Language codes
    private const DEFAULT = 'en-GB';
    private const PARENT = 'de-DE';
    private const CHILD = 'de-TEST';

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var array
     */
    private $salesChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new TestDataCollection();

        // Get language ids
        $this->ids->set(self::DEFAULT, Defaults::LANGUAGE_SYSTEM);
        $this->ids->set(self::PARENT, $this->getDeDeLanguageId());
        $this->ids->create(self::CHILD);

        // Create storefront saleschannel for child language
        $this->salesChannel = $this->createSalesChannel([
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
                    'url' => 'http://localhost',
                ],
            ],
        ]);
    }

    /**
     * Checks whether the seo url updater is using the correct language for translations.
     *
     * @dataProvider seoLanguageDataProvider
     */
    public function testSeoLanguageInheritance(array $translations, string $pathInfo): void
    {
        // Create landing page (triggers seo url updater)
        $this->getContainer()->get('landing_page.repository')->upsert([
            [
                'id' => $id = UUID::randomHex(),
                'translations' => array_map(function (string $translation): array {
                    return [
                        'name' => $translation,
                        'url' => $translation,
                        'languageId' => $this->ids->get($translation),
                    ];
                }, $translations),
                'salesChannels' => [['id' => $this->salesChannel['id']]],
            ],
        ], $this->ids->getContext());

        // Search for created seo url
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $id));
        $criteria->addFilter(new EqualsFilter('routeName', LandingPageSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $this->salesChannel['id']));
        $seoUrl = $this->getContainer()->get('seo_url.repository')->search(
            $criteria,
            $this->ids->getContext()
        )->first();

        // Check if seo url was created
        static::assertNotNull($seoUrl);

        // Check if seo path matches the expected path
        static::assertStringStartsWith($pathInfo, $seoUrl->getSeoPathInfo());
    }

    public function seoLanguageDataProvider(): array
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
