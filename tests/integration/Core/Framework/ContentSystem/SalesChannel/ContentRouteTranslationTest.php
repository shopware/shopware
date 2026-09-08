<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the write-to-serve round trip of a translatable property: a `Sw:Content:Text.text` language map is
 * persisted through the DAL and served through the real `ContentRoute`, once per requested language.
 *
 * Three languages back the fallback cases, because the language chain a `SalesChannelContext` carries is
 * `[requested, its parent, system]`: the system language, a parentless regional language, and a dialect
 * inheriting from that regional one. The fixture map carries the system and regional entries only, so a dialect
 * request is the case where reduction has to walk past the first chain entry to find one the map holds.
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
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const ANCHOR_TEXT = 'System language copy';

    private const REGIONAL_TEXT = 'Regional language copy';

    private const DANGLING_TEXT = 'Copy for a language that does not exist';

    private const LAYOUT_NAME = 'content-route-translation';

    private const LAYOUT_VERSION = '1.0.0';

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

    #[TestDox('serves the anchor entry of a translatable property for a system-language request')]
    public function testSystemLanguageRequestServesTheAnchorEntry(): void
    {
        $this->persistTextLayout([
            Defaults::LANGUAGE_SYSTEM => self::ANCHOR_TEXT,
            $this->ids->get('language-regional') => self::REGIONAL_TEXT,
        ]);

        static::assertSame(self::ANCHOR_TEXT, $this->servedProperties(null)['text'] ?? null);
    }

    #[TestDox('serves the requested language own entry of a translatable property')]
    public function testRequestedLanguageServesItsOwnEntry(): void
    {
        $this->persistTextLayout([
            Defaults::LANGUAGE_SYSTEM => self::ANCHOR_TEXT,
            $this->ids->get('language-regional') => self::REGIONAL_TEXT,
        ]);

        static::assertSame(self::REGIONAL_TEXT, $this->servedProperties($this->ids->get('language-regional'))['text'] ?? null);
    }

    #[TestDox('serves the parent entry for a requested language the map carries no entry for')]
    public function testRequestedLanguageWithoutItsOwnEntryServesTheParentEntry(): void
    {
        $this->persistTextLayout([
            Defaults::LANGUAGE_SYSTEM => self::ANCHOR_TEXT,
            $this->ids->get('language-regional') => self::REGIONAL_TEXT,
        ]);

        static::assertSame(self::REGIONAL_TEXT, $this->servedProperties($this->ids->get('language-dialect'))['text'] ?? null);
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
        $context = Context::createDefaultContext();

        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Content route translation category',
            'active' => true,
        ]], $context);

        $this->layoutRepository()->create([[
            'id' => $this->ids->get('layout'),
            'name' => self::LAYOUT_NAME,
            'version' => self::LAYOUT_VERSION,
            'rootSource' => 'category',
            'layout' => [[
                'id' => $this->ids->get('text'),
                'component' => 'Sw:Content:Text',
                'properties' => ['text' => $languageMap],
            ]],
        ]], $context);

        $this->repository('category_content_layout.repository')->create([[
            'id' => $this->ids->get('assignment'),
            'categoryId' => $this->ids->get('category'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], $context);
    }

    /**
     * The rendered property map of the served root element, requested under the given language. A null
     * language id sends no header, which is the sales channel's own language — the system language here.
     * The map rather than the `text` value alone, so a caller can assert the key is absent.
     *
     * @return array<string, mixed>
     */
    private function servedProperties(?string $languageId): array
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
        static::assertSame($this->ids->get('text'), $body['elements'][0]['id'] ?? null);

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

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $serviceId): EntityRepository
    {
        $repository = static::getContainer()->get($serviceId);
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
