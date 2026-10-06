<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\SalesChannel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Lifecycle\Handler\ContentSystemElementTypeLifecycleHandler;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\DatabaseTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Integration\Core\Framework\ContentSystem\ContentLayoutFixtureBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the write-to-serve round trip of a translatable property: a `Sw:Content:Text.text` language map is
 * persisted through the DAL and served through the real `ContentRoute`, once per requested language. The typed
 * cases do the same for a translatable boolean: they install a fixture app that registers the element type,
 * then assert the served value is the plain boolean of the selected entry, falling back to the anchor entry.
 *
 * Three languages back the fallback cases, because the language chain a `SalesChannelContext` carries for a
 * non-system language is `[requested, its parent if it has one, system]`: the system language, a parentless
 * regional language, and a dialect inheriting from that regional one. The reduction-chain cases store the system
 * and regional entries only, so a dialect request is the case where reduction has to walk past the first chain
 * entry to find one the map holds.
 *
 * All three are members of the browser's sales channel. A language outside `sales_channel_language` is refused
 * by the context factory before any content code runs, so membership is what makes these requests reach
 * reduction at all rather than a story about it.
 *
 * @internal
 */
#[Package('framework')]
#[Group('store-api')]
class ContentRouteTranslationTest extends TestCase
{
    use ContentLayoutFixtureBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const ANCHOR_TEXT = 'System language copy';

    private const REGIONAL_TEXT = 'Regional language copy';

    private const DANGLING_TEXT = 'Copy for a language that does not exist';

    private const LAYOUT_NAME = 'content-route-translation';

    private const LAYOUT_VERSION = '1.0.0';

    /**
     * The boolean fixture type the typed reduction case serves. It ships as an app element type because the
     * core definitions directory is fixed by the compiler pass and carries no test types: the app tier is the
     * only route by which a test can register a type whose translatable property is not a string.
     */
    private const TOGGLE_APP_FIXTURE = __DIR__ . '/../_fixtures/typed-translatable-toggle';

    private const TOGGLE_COMPONENT = 'content-route-translation-toggle:Toggle';

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->createLanguages();

        // The languages must exist before the sales channel that lists them, so the browser is built here
        // rather than by the trait's lazy accessor.
        $this->browser = $this->createSalesChannelBrowser(salesChannelOverrides: [
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $this->ids->get('language-regional')],
                ['id' => $this->ids->get('language-dialect')],
            ],
        ]);
    }

    /**
     * Stays its own method rather than a provider row: this case sends no language header at all, which is not
     * a language the provider could name.
     */
    #[TestDox('serves the anchor entry for a request carrying no language header')]
    public function testSystemLanguageRequestServesTheAnchorEntry(): void
    {
        $this->persistTextLayout([
            Defaults::LANGUAGE_SYSTEM => self::ANCHOR_TEXT,
            $this->ids->get('language-regional') => self::REGIONAL_TEXT,
        ]);

        static::assertSame(self::ANCHOR_TEXT, $this->servedProperties(null)['text'] ?? null);
    }

    /**
     * The requested language is named rather than passed as an id: the ids collection is instance state and a
     * data provider is static, so the row carries the name and the test resolves it.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function reductionChainProvider(): iterable
    {
        yield 'requested language serves its own entry' => ['language-regional', self::REGIONAL_TEXT];
        yield 'dialect without its own entry serves the parent entry' => ['language-dialect', self::REGIONAL_TEXT];
    }

    #[DataProvider('reductionChainProvider')]
    #[TestDox('serves the entry the reduction chain selects: $_dataName')]
    public function testReductionChainServesExpectedEntry(string $requestedLanguage, string $expectedText): void
    {
        $this->persistTextLayout([
            Defaults::LANGUAGE_SYSTEM => self::ANCHOR_TEXT,
            $this->ids->get('language-regional') => self::REGIONAL_TEXT,
        ]);

        static::assertSame($expectedText, $this->servedProperties($this->ids->get($requestedLanguage))['text'] ?? null);
    }

    /**
     * Key existence against the `language` table is not a write constraint: the write judges the key format
     * alone, so a map entry naming no language row is stored verbatim. That is what makes the dangling entry a
     * diagnostics warning rather than a rejection.
     */
    #[TestDox('stores a translatable entry keyed by a language id no language row carries')]
    public function testAnEntryForANonExistentLanguageIsStoredVerbatim(): void
    {
        $map = [
            Defaults::LANGUAGE_SYSTEM => self::ANCHOR_TEXT,
            $this->ids->get('dangling-language') => self::DANGLING_TEXT,
        ];

        $this->persistTextLayout($map);

        $text = $this->storedRoots()[0]->property('text');
        static::assertNotNull($text);
        // assertEquals, not assertSame: a stored JSON map's key order is not part of this behaviour (MySQL
        // reorders object keys, MariaDB does not).
        static::assertEquals($map, $text->jsonSerialize());
    }

    /**
     * The map carries the dangling entry and nothing else, so there is no second entry a wrong selection
     * could land on: any implementation that served a key outside the chain would produce the string. It
     * reduces to the null variant instead, which the rendered-tree mint skips, so the key is absent.
     */
    #[TestDox('serves no value at all for a map whose only entry is outside the request language chain')]
    public function testAMapWithNoChainEntryServesTheKeyAsUnset(): void
    {
        $this->persistTextLayout([$this->ids->get('dangling-language') => self::DANGLING_TEXT]);

        static::assertArrayNotHasKey('text', $this->servedProperties(null));
    }

    /**
     * The dialect's chain has three positions: [dialect, regional, system]. The map carries only the system
     * entry, so the reduction loop must walk past both the dialect and the regional position before it finds
     * a match. `StoredTreePreparer::selectTranslation()` walks the whole `$languageIdChain` in one `foreach`
     * with no cap on how many entries it inspects; a regression that bounded the walk to the chain's first
     * two positions would still pass every other test in this file (each of them resolves at position 0 or
     * 1) and only this assertion would catch it.
     */
    #[TestDox('serves the anchor entry from the boundary of a three-position chain when nothing before it matches')]
    public function testRequestedLanguageWithNeitherItsOwnNorItsParentEntryWalksToTheChainBoundary(): void
    {
        $this->persistTextLayout([Defaults::LANGUAGE_SYSTEM => self::ANCHOR_TEXT]);

        static::assertSame(self::ANCHOR_TEXT, $this->servedProperties($this->ids->get('language-dialect'))['text'] ?? null);
    }

    /**
     * The typed counterpart of the string reduction cases: a translatable boolean is reduced through the same
     * chain and served as the plain boolean, never as a language map and never coerced to a string. The two
     * entries differ, so the served value proves which entry the chain selected rather than a constant the map
     * would carry either way.
     */
    #[TestDox('serves the reduced entry of a translatable boolean per requested language')]
    public function testTypedTranslatablePropertyIsServedPerRequestedLanguage(): void
    {
        $this->installToggleElementType();

        $this->persistToggleLayout([
            Defaults::LANGUAGE_SYSTEM => false,
            $this->ids->get('language-regional') => true,
        ]);

        static::assertFalse($this->servedProperties(null, 'toggle')['enabled'] ?? null);
        static::assertTrue($this->servedProperties($this->ids->get('language-regional'), 'toggle')['enabled'] ?? null);
        static::assertTrue($this->servedProperties($this->ids->get('language-dialect'), 'toggle')['enabled'] ?? null);
    }

    /**
     * Anchor fallback on the typed shape: the map carries the anchor entry alone, so the dialect's request has
     * to walk its whole chain before it reaches it. The `false` anchor is served as a boolean, which is the case
     * a truthiness-based reduction or a string coercion would lose.
     */
    #[TestDox('falls back to the anchor entry of a translatable boolean')]
    public function testTypedTranslatablePropertyFallsBackToTheAnchorEntry(): void
    {
        $this->installToggleElementType();

        $this->persistToggleLayout([Defaults::LANGUAGE_SYSTEM => false]);

        static::assertFalse($this->servedProperties($this->ids->get('language-dialect'), 'toggle')['enabled'] ?? null);
    }

    /**
     * A parentless regional language plus a dialect inheriting from it. `LanguageValidator` allows one level of
     * nesting only, so the dialect's parent must itself have no parent — which rules out hanging the pair off
     * the system language and is why the regional row is a root of its own. Each needs its own locale row,
     * because `language.translation_code_id` is required on a root language and no shipped locale is free.
     */
    private function createLanguages(): void
    {
        $this->repository('language.repository')->create([
            [
                'id' => $this->ids->create('language-regional'),
                'name' => 'Content translation regional',
                'active' => true,
                'locale' => [
                    'id' => $this->ids->create('locale-regional'),
                    'name' => 'Content translation regional',
                    'territory' => 'Content translation territory',
                    'code' => 'sw-KE-regional',
                ],
                'translationCodeId' => $this->ids->get('locale-regional'),
            ],
            [
                'id' => $this->ids->create('language-dialect'),
                'name' => 'Content translation dialect',
                'parentId' => $this->ids->get('language-regional'),
                'active' => true,
                'locale' => [
                    'id' => $this->ids->create('locale-dialect'),
                    'name' => 'Content translation dialect',
                    'territory' => 'Content translation territory',
                    'code' => 'sw-TZ-dialect',
                ],
                'translationCodeId' => $this->ids->get('locale-dialect'),
            ],
        ], Context::createDefaultContext());
    }

    /**
     * One `Sw:Content:Text` root holding the given language map, bound to a category the request addresses by
     * path. `text` is the only translatable property the type declares and it is not required, so the map is
     * the whole subject of the fixture.
     *
     * @param array<string, string> $languageMap
     */
    private function persistTextLayout(array $languageMap): void
    {
        $this->createTestCategory($this->ids->create('category'), 'Content route translation category');

        $this->persistContentLayout(
            $this->ids->get('layout'),
            self::LAYOUT_NAME,
            self::LAYOUT_VERSION,
            'category',
            [[
                'id' => $this->ids->get('text'),
                'component' => 'Sw:Content:Text',
                'properties' => ['text' => $languageMap],
            ]],
        );

        $this->assignLayoutToCategory(
            $this->ids->get('assignment'),
            $this->ids->get('category'),
            $this->ids->get('layout'),
        );
    }

    /**
     * Registers the boolean fixture type, then reads it back through `DatabaseTypeLoader` and asserts the type
     * and its `enabled` property survived the persist-and-decode round trip.
     *
     * `DatabaseTypeLoader` reads only active apps (`WHERE a.active = 1`); `AppFixture` already creates the app
     * active, so no explicit activation precedes the read.
     */
    private function installToggleElementType(): void
    {
        $manifest = $this->appFixture()->loadManifest(self::TOGGLE_APP_FIXTURE . '/manifest.xml');
        $app = $this->appFixture()->createApp($manifest);

        $installContext = $this->appFixture()->createInstallContext($app, $manifest, new Filesystem($manifest->getPath()));

        $elementTypeHandler = static::getContainer()->get(ContentSystemElementTypeLifecycleHandler::class);
        static::assertInstanceOf(ContentSystemElementTypeLifecycleHandler::class, $elementTypeHandler);
        $elementTypeHandler->install($installContext);

        $loader = static::getContainer()->get(DatabaseTypeLoader::class);
        static::assertInstanceOf(DatabaseTypeLoader::class, $loader);

        $specification = null;
        foreach ($loader->load() as $loaded) {
            if ($loaded->name() === self::TOGGLE_COMPONENT) {
                $specification = $loaded;
                break;
            }
        }

        static::assertInstanceOf(
            ContentSystemElementTypeSpecification::class,
            $specification,
            'The fixture element type must round-trip through DatabaseTypeLoader.',
        );

        $enabled = $specification->properties()['enabled'] ?? null;
        static::assertNotNull($enabled, 'The fixture type must declare an "enabled" property.');
        static::assertSame('boolean', $enabled->type()->type());
        static::assertTrue($enabled->type()->translatable());
    }

    /**
     * One boolean fixture root holding the given language map. `enabled` is the type's only property and is not
     * required, so the map is the whole subject of the fixture.
     *
     * @param array<string, bool> $languageMap
     */
    private function persistToggleLayout(array $languageMap): void
    {
        $this->createTestCategory($this->ids->create('category'), 'Content route translation category');

        $this->persistContentLayout(
            $this->ids->get('layout'),
            self::LAYOUT_NAME,
            self::LAYOUT_VERSION,
            'category',
            [[
                'id' => $this->ids->get('toggle'),
                'component' => self::TOGGLE_COMPONENT,
                'properties' => ['enabled' => $languageMap],
            ]],
        );

        $this->assignLayoutToCategory(
            $this->ids->get('assignment'),
            $this->ids->get('category'),
            $this->ids->get('layout'),
        );
    }

    private function appFixture(): AppFixture
    {
        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);

        return $appFixture;
    }

    /**
     * The rendered property map of the served root element, requested under the given language. A null
     * language id sends no header, which is the sales channel's own language — the system language here.
     * The map rather than the `text` value alone, so a caller can assert the key is absent.
     *
     * The element id is keyed rather than passed, because the ids collection is instance state resolved by the
     * caller's key; the typed cases serve a different root and pass their own key.
     *
     * @return array<string, mixed>
     */
    private function servedProperties(?string $languageId, string $elementIdKey = 'text'): array
    {
        if ($languageId !== null) {
            $this->browser->setServerParameter(
                'HTTP_' . str_replace('-', '_', mb_strtoupper(PlatformRequest::HEADER_LANGUAGE_ID)),
                $languageId,
            );
        }

        $this->browser->request('GET', '/store-api/content/category/' . $this->ids->get('category'));

        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertIsArray($body['elements'] ?? null);
        static::assertIsArray($body['elements'][0] ?? null);
        static::assertSame($this->ids->get($elementIdKey), $body['elements'][0]['id'] ?? null);

        $properties = $body['elements'][0]['properties'] ?? null;
        static::assertIsArray($properties);

        return $properties;
    }

    /**
     * @return list<StoredElement>
     */
    private function storedRoots(): array
    {
        $layout = $this->layoutRepository()
            ->search(new Criteria([$this->ids->get('layout')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($layout);

        return $layout->getLayout();
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function layoutRepository(): EntityRepository
    {
        $repository = static::getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
