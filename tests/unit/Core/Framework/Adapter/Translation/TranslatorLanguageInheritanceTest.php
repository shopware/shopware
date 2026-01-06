<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator as SymfonyTranslator;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Tests for the language inheritance fix in snippet fallback.
 *
 * This test verifies that when a language is configured with a parent language
 * (e.g., Spanish inherits from English), the catalogue fallback chain is set up
 * correctly so that snippets fall back to the parent language.
 *
 * The key behavior:
 * - Each catalogue in the chain loads its own locale's snippets
 * - The fallback mechanism works through the catalogue chain (e.g., es-ES -> en-GB)
 * - This ensures Spanish views show English snippets when Spanish doesn't exist,
 *   instead of falling back to German (system default)
 *
 * @internal
 */
#[CoversClass(Translator::class)]
class TranslatorLanguageInheritanceTest extends TestCase
{
    /**
     * Tests that when a language has a configured parent (via language chain),
     * the catalogue fallback chain is set up correctly using language inheritance.
     *
     * Scenario:
     * - Current language: Spanish (es-ES) with parent English (en-GB)
     * - Language chain: [spanish-uuid, english-uuid]
     * - Expected: es-ES catalogue has en-GB as fallback catalogue
     * - Each catalogue loads its own locale's snippets
     */
    public function testGetCatalogueUsesLanguageInheritanceForFallback(): void
    {
        $spanishLanguageId = Uuid::randomHex();
        $englishLanguageId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();

        $decorated = $this->createMock(SymfonyTranslator::class);
        $originCatalogue = new MessageCatalogue('es-ES', [
            'messages' => [
                'global.title' => 'Title in catalogue',
            ],
        ]);
        $fallbackCatalogue = new MessageCatalogue('en-GB', [
            'messages' => [
                'global.title' => 'English title',
            ],
        ]);

        // Return different catalogues for different locales to avoid circular reference
        $decorated->method('getCatalogue')->willReturnCallback(
            static fn (?string $locale = null) => $locale === 'en-GB' ? $fallbackCatalogue : $originCatalogue
        );
        $decorated->method('getLocale')->willReturn('es-ES');

        // Create SalesChannelContext with language chain (Spanish -> English)
        $context = $this->createMock(Context::class);
        $context->method('getLanguageIdChain')->willReturn([$spanishLanguageId, $englishLanguageId]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getLanguageIdChain')->willReturn([$spanishLanguageId, $englishLanguageId]);
        $salesChannelContext->method('getContext')->willReturn($context);

        // Create request with SalesChannelContext
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $snippetSetId);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        // Mock LanguageLocaleCodeProvider to return en-GB for the parent language
        $localeCodeProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $localeCodeProvider->method('getLocaleForLanguageId')
            ->with($englishLanguageId)
            ->willReturn('en-GB');

        // Each catalogue loads its own locale's snippets
        // The fallback mechanism works through the catalogue chain (es-ES -> en-GB)
        $snippetService = $this->createMock(SnippetService::class);
        $snippetService->expects($this->atLeastOnce())
            ->method('getStorefrontSnippets')
            ->willReturn([
                'global.title' => 'Title from snippets',
            ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([$snippetSetId]);

        $cache = $this->createMock(CacheInterface::class);
        $item = new CacheItem();
        $property = new \ReflectionProperty(CacheItem::class, 'isTaggable');
        $property->setValue($item, true);

        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) use ($item) {
            return $callback($item);
        });

        $translator = new Translator(
            $decorated,
            $requestStack,
            $cache,
            $this->createMock(MessageFormatterInterface::class),
            'prod',
            $connection,
            $localeCodeProvider,
            $snippetService,
            $this->createMock(CacheTagCollector::class),
        );

        $snippetSetIdProp = new \ReflectionProperty(Translator::class, 'snippetSetId');
        $snippetSetIdProp->setValue($translator, $snippetSetId);

        $catalogue = $translator->getCatalogue('es-ES');

        // Verify catalogue was created with correct locale
        static::assertNotNull($catalogue);
        static::assertSame('es-ES', $catalogue->getLocale());

        // Verify the language inheritance fallback chain is set up (es-ES -> en-GB)
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertNotNull($fallback, 'Expected en-GB fallback catalogue based on language inheritance');
        static::assertSame('en-GB', $fallback->getLocale());
    }

    /**
     * Tests that when no SalesChannelContext is available,
     * the translator falls back to the original behavior (locale prefix).
     */
    public function testGetCatalogueFallsBackToLocalePrefixWithoutSalesChannelContext(): void
    {
        $snippetSetId = Uuid::randomHex();

        $decorated = $this->createMock(SymfonyTranslator::class);
        $originCatalogue = new MessageCatalogue('es-ES', [
            'messages' => [
                'global.title' => 'Title in catalogue',
            ],
        ]);

        $decorated->method('getCatalogue')->willReturn($originCatalogue);
        $decorated->method('getLocale')->willReturn('es-ES');

        // Create request WITHOUT SalesChannelContext
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $snippetSetId);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $localeCodeProvider = $this->createMock(LanguageLocaleCodeProvider::class);

        // Each catalogue loads its own locale's snippets
        $snippetService = $this->createMock(SnippetService::class);
        $snippetService->expects($this->once())
            ->method('getStorefrontSnippets')
            ->with(
                static::anything(),
                $snippetSetId,
                'es-ES', // Catalogue loads snippets for its own locale
                static::anything()
            )
            ->willReturn([
                'global.title' => 'Title from snippets',
            ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([$snippetSetId]);

        $cache = $this->createMock(CacheInterface::class);
        $item = new CacheItem();
        $property = new \ReflectionProperty(CacheItem::class, 'isTaggable');
        $property->setValue($item, true);

        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) use ($item) {
            return $callback($item);
        });

        $translator = new Translator(
            $decorated,
            $requestStack,
            $cache,
            $this->createMock(MessageFormatterInterface::class),
            'prod',
            $connection,
            $localeCodeProvider,
            $snippetService,
            $this->createMock(CacheTagCollector::class),
        );

        $snippetSetIdProp = new \ReflectionProperty(Translator::class, 'snippetSetId');
        $snippetSetIdProp->setValue($translator, $snippetSetId);

        $catalogue = $translator->getCatalogue('es-ES');

        static::assertNotNull($catalogue);
    }

    /**
     * Tests that when language chain has only one language (no parent),
     * the translator falls back to the original behavior (locale prefix).
     */
    public function testGetCatalogueFallsBackToLocalePrefixWithNoParentLanguage(): void
    {
        $englishLanguageId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();

        $decorated = $this->createMock(SymfonyTranslator::class);
        $originCatalogue = new MessageCatalogue('en-GB', [
            'messages' => [
                'global.title' => 'Title in catalogue',
            ],
        ]);

        $decorated->method('getCatalogue')->willReturn($originCatalogue);
        $decorated->method('getLocale')->willReturn('en-GB');

        // Create SalesChannelContext with single language (no parent)
        $context = $this->createMock(Context::class);
        $context->method('getLanguageIdChain')->willReturn([$englishLanguageId]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getLanguageIdChain')->willReturn([$englishLanguageId]);
        $salesChannelContext->method('getContext')->willReturn($context);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $snippetSetId);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $localeCodeProvider = $this->createMock(LanguageLocaleCodeProvider::class);

        // Each catalogue loads its own locale's snippets
        $snippetService = $this->createMock(SnippetService::class);
        $snippetService->expects($this->once())
            ->method('getStorefrontSnippets')
            ->with(
                static::anything(),
                $snippetSetId,
                'en-GB', // Catalogue loads snippets for its own locale
                static::anything()
            )
            ->willReturn([
                'global.title' => 'Title from snippets',
            ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([$snippetSetId]);

        $cache = $this->createMock(CacheInterface::class);
        $item = new CacheItem();
        $property = new \ReflectionProperty(CacheItem::class, 'isTaggable');
        $property->setValue($item, true);

        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) use ($item) {
            return $callback($item);
        });

        $translator = new Translator(
            $decorated,
            $requestStack,
            $cache,
            $this->createMock(MessageFormatterInterface::class),
            'prod',
            $connection,
            $localeCodeProvider,
            $snippetService,
            $this->createMock(CacheTagCollector::class),
        );

        $snippetSetIdProp = new \ReflectionProperty(Translator::class, 'snippetSetId');
        $snippetSetIdProp->setValue($translator, $snippetSetId);

        $catalogue = $translator->getCatalogue('en-GB');

        static::assertNotNull($catalogue);
    }
}
