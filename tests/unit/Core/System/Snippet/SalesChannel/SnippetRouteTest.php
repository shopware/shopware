<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Snippet\SalesChannel\SnippetRoute;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetRoute::class)]
class SnippetRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    private string $snippetSetId;

    protected function setUp(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $this->salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel
        );

        $this->snippetSetId = Uuid::randomHex();
    }

    public function testLoadReturnsMergedSnippetsOfTheCatalogueChainSortedByKey(): void
    {
        $catalogue = new MessageCatalogue('pl-PL', ['messages' => [
            'zeta.key' => 'Z',
            'checkout.cart.title' => 'Koszyk',
        ]]);
        $catalogue->addFallbackCatalogue(new MessageCatalogue('en-GB', ['messages' => [
            'checkout.cart.title' => 'Cart',
            'only.fallback' => 'Fallback value',
        ]]));

        $route = $this->createRoute(['pl-PL' => $catalogue]);

        $response = $route->load(new Request(), $this->salesChannelContext);

        // without languageIds the response contains exactly one set: the context language
        static::assertCount(1, $response->getResult()->sets);
        $result = $response->getResult()->sets[0];

        static::assertSame($this->salesChannelContext->getLanguageId(), $result->languageId);
        static::assertSame('pl-PL', $result->locale);
        static::assertSame('pl', $result->fallbackLocale);
        static::assertSame($this->snippetSetId, $result->snippetSetId);

        // the current locale wins over the fallback, fallback-only keys survive, keys are sorted
        static::assertSame(
            [
                'checkout.cart.title' => 'Koszyk',
                'only.fallback' => 'Fallback value',
                'zeta.key' => 'Z',
            ],
            $result->snippets
        );

        static::assertSame(Hasher::hash($result->snippets), $result->hash);
        static::assertSame('"' . $result->hash . '"', $response->getEtag());
    }

    public function testPrefixesMatchWholeKeySegments(): void
    {
        $catalogue = new MessageCatalogue('pl-PL', ['messages' => [
            'checkout' => 'Root key',
            'checkout.cart.title' => 'Koszyk',
            'checkoutConfirm.title' => 'Same string prefix, other namespace',
            'account.login' => 'Login',
        ]]);

        $route = $this->createRoute(['pl-PL' => $catalogue]);

        $response = $route->load(new Request(['prefixes' => 'checkout']), $this->salesChannelContext);
        $result = $response->getResult()->sets[0];

        static::assertSame(
            [
                'checkout' => 'Root key',
                'checkout.cart.title' => 'Koszyk',
            ],
            $result->snippets
        );
    }

    public function testTrailingDotAndPrefixOrderDoNotChangeTheResult(): void
    {
        $catalogue = new MessageCatalogue('pl-PL', ['messages' => [
            'checkout.cart.title' => 'Koszyk',
            'account.login' => 'Login',
            'general.home' => 'Home',
        ]]);

        $route = $this->createRoute(['pl-PL' => $catalogue]);

        $first = $route->load(new Request(['prefixes' => 'checkout.,account']), $this->salesChannelContext);
        $second = $route->load(new Request(['prefixes' => 'account.,checkout']), $this->salesChannelContext);

        $firstResult = $first->getResult()->sets[0];
        $secondResult = $second->getResult()->sets[0];

        static::assertSame($firstResult->snippets, $secondResult->snippets);
        static::assertSame($firstResult->hash, $secondResult->hash);
    }

    public function testReturnsNotModifiedWhenIfNoneMatchMatchesTheEtag(): void
    {
        $catalogue = new MessageCatalogue('pl-PL', ['messages' => ['checkout.cart.title' => 'Koszyk']]);

        $route = $this->createRoute(['pl-PL' => $catalogue]);

        $etag = $route->load(new Request(), $this->salesChannelContext)->getEtag();
        static::assertNotNull($etag);

        $request = new Request();
        $request->headers->set('If-None-Match', $etag);

        $response = $route->load($request, $this->salesChannelContext);

        static::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        static::assertSame($etag, $response->getEtag());
    }

    public function testMultipleLanguagesReturnSetsSortedByLanguageId(): void
    {
        $languageIds = [Uuid::randomHex(), Uuid::randomHex()];
        sort($languageIds);
        [$firstLanguageId, $secondLanguageId] = $languageIds;

        $catalogues = [
            'pl-PL' => new MessageCatalogue('pl-PL', ['messages' => ['checkout.cart.title' => 'Koszyk']]),
            'de-DE' => new MessageCatalogue('de-DE', ['messages' => ['checkout.cart.title' => 'Warenkorb']]),
        ];

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($languageIds);

        $route = $this->createRoute(
            $catalogues,
            locales: [$firstLanguageId => 'pl-PL', $secondLanguageId => 'de-DE'],
            connection: $connection
        );

        // request order is reversed on purpose, the response is normalized to ascending language ids
        $request = new Request(['languageIds' => $secondLanguageId . ',' . $firstLanguageId]);
        $result = $route->load($request, $this->salesChannelContext)->getResult();

        static::assertCount(2, $result->sets);
        static::assertSame($firstLanguageId, $result->sets[0]->languageId);
        static::assertSame('pl-PL', $result->sets[0]->locale);
        static::assertSame($secondLanguageId, $result->sets[1]->languageId);
        static::assertSame('de-DE', $result->sets[1]->locale);
    }

    public function testThrowsWhenLanguageIsNotAssignedToTheSalesChannel(): void
    {
        $languageId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $route = $this->createRoute([], connection: $connection);

        $this->expectExceptionObject(
            SnippetException::languageNotAvailableInSalesChannel($languageId, $this->salesChannelContext->getSalesChannelId())
        );

        $route->load(new Request(['languageIds' => $languageId]), $this->salesChannelContext);
    }

    public function testGetDecoratedThrows(): void
    {
        $route = $this->createRoute([]);

        $this->expectExceptionObject(new DecorationPatternException(SnippetRoute::class));

        $route->getDecorated();
    }

    public function testThrowsWhenTooManyPrefixesAreRequested(): void
    {
        $route = $this->createRoute([]);

        // one over the limit, after deduplication
        $prefixes = array_map(
            static fn (int $index): string => 'namespace' . $index,
            range(0, SnippetRoute::MAX_PREFIXES)
        );

        $this->expectExceptionObject(
            SnippetException::tooManyPrefixes(SnippetRoute::MAX_PREFIXES + 1, SnippetRoute::MAX_PREFIXES)
        );

        $route->load(new Request(['prefixes' => implode(',', $prefixes)]), $this->salesChannelContext);
    }

    public function testThrowsOnMalformedLanguageId(): void
    {
        $route = $this->createRoute([]);

        $this->expectExceptionObject(
            SnippetException::languageNotAvailableInSalesChannel('not-a-uuid', $this->salesChannelContext->getSalesChannelId())
        );

        $route->load(new Request(['languageIds' => 'not-a-uuid']), $this->salesChannelContext);
    }

    /**
     * @param array<string, MessageCatalogue> $catalogues locale => catalogue served by the translator
     * @param array<string, string>|null $locales languageId => locale, defaults to the context language mapping to pl-PL
     */
    private function createRoute(array $catalogues, ?array $locales = null, ?Connection $connection = null): SnippetRoute
    {
        $locales ??= [$this->salesChannelContext->getLanguageId() => 'pl-PL'];

        $translator = static::createStub(AbstractTranslator::class);
        $translator->method('getCatalogue')->willReturnCallback(
            static fn (?string $locale): MessageCatalogue => $catalogues[(string) $locale] ?? new MessageCatalogue((string) $locale)
        );
        $translator->method('getSnippetSetId')->willReturn($this->snippetSetId);

        $languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $languageLocaleProvider->method('getLocaleForLanguageId')->willReturnCallback(
            static fn (string $languageId): string => $locales[$languageId] ?? 'en-GB'
        );

        return new SnippetRoute(
            $translator,
            $languageLocaleProvider,
            $connection ?? static::createStub(Connection::class),
            static::createStub(CacheTagCollector::class)
        );
    }
}
