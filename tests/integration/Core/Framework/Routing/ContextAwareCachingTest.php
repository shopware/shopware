<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;

/**
 * @internal
 */
class ContextAwareCachingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testContextHeadersInStoreApiResponse(): void
    {
        $browser = $this->createCustomSalesChannelBrowser();

        // Make a request to a store-api endpoint
        $browser->request('GET', '/store-api/category');

        $response = $browser->getResponse();

        // Check that context headers are present
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CURRENCY_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_HASH));
        static::assertTrue($response->headers->has('Vary'));

        // Check that vary header contains our context headers
        $varyHeader = $response->headers->get('Vary');
        static::assertNotNull($varyHeader);
        static::assertStringContainsString(PlatformRequest::HEADER_LANGUAGE_ID, $varyHeader);
        static::assertStringContainsString(PlatformRequest::HEADER_CURRENCY_ID, $varyHeader);
        static::assertStringContainsString(PlatformRequest::HEADER_CONTEXT_HASH, $varyHeader);
    }

    public function testCurrencyAndLanguageUpdate(): void
    {
        $defaultLanguageId = Defaults::LANGUAGE_SYSTEM;
        $defaultCurrencyId = Defaults::CURRENCY;
        $newLanguageId = $this->getDeDeLanguageId();
        $newCurrencyId = $this->getCurrencyIdByIso('USD');
        static::assertNotEmpty($newCurrencyId, 'USD currency should exist in the test database');

        // Need to create a custom sales channel with multiple languages and currencies
        $browser = $this->createCustomSalesChannelBrowser([
            'languages' => [
                ['id' => $defaultLanguageId],
                ['id' => $newLanguageId],
            ],
            'currencies' => [
                ['id' => $defaultCurrencyId],
                ['id' => $newCurrencyId],
            ],
        ]);

        // Make first request to get initial context hash
        $browser->request('GET', '/store-api/product');
        $response1 = $browser->getResponse();

        // Check that initial response has default context headers
        $initialHash = $response1->headers->get(PlatformRequest::HEADER_CONTEXT_HASH);
        static::assertSame($defaultLanguageId, $response1->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertSame($defaultCurrencyId, $response1->headers->get(PlatformRequest::HEADER_CURRENCY_ID));

        // Set a different language and currency using real IDs
        $browser->setServerParameter($this->headerToServerParameter(PlatformRequest::HEADER_LANGUAGE_ID), $newLanguageId);
        $browser->setServerParameter($this->headerToServerParameter(PlatformRequest::HEADER_CURRENCY_ID), $newCurrencyId);

        // Make second request to a store-api endpoint
        $browser->request('POST', '/store-api/search');
        $response2 = $browser->getResponse();

        // Check that second response has updated context headers
        static::assertSame($newLanguageId, $response2->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertSame($newCurrencyId, $response2->headers->get(PlatformRequest::HEADER_CURRENCY_ID));
        static::assertNotSame($initialHash, $response2->headers->get(PlatformRequest::HEADER_CONTEXT_HASH));
    }

    public function testContextHashIsConsistentForSameContext(): void
    {
        $browser = $this->createCustomSalesChannelBrowser();

        // Make two requests with the same context
        $browser->request('GET', '/store-api/category');
        $response1 = $browser->getResponse();
        $hash1 = $response1->headers->get(PlatformRequest::HEADER_CONTEXT_HASH);

        $browser->request('GET', '/store-api/product');
        $response2 = $browser->getResponse();
        $hash2 = $response2->headers->get(PlatformRequest::HEADER_CONTEXT_HASH);

        // Context hash should be the same for the same context
        static::assertNotNull($hash1);
        static::assertSame($hash1, $hash2);
    }

    public function testNonStoreApiRouteDoesNotHaveContextHeaders(): void
    {
        $browser = $this->createCustomSalesChannelBrowser();

        // Make a request to a non-store-api endpoint
        $browser->request('GET', '/');

        $response = $browser->getResponse();

        // Check that context headers are not present for non-store-api routes
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CURRENCY_ID));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_HASH));
    }

    private function headerToServerParameter(string $header): string
    {
        return 'HTTP_' . strtoupper(str_replace('-', '_', $header));
    }
}
