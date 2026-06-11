<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('framework')]
#[Group('store-api')]
class StoreApiDomainResolverTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const EN_DOMAIN = 'http://sw-domain-resolver-test.test';
    private const DE_DOMAIN = 'http://sw-domain-resolver-test.test/de';

    private KernelBrowser $browser;

    private string $deDeLanguageId;

    private string $eurCurrencyId;

    private string $usdCurrencyId;

    protected function setUp(): void
    {
        $this->deDeLanguageId = $this->getDeDeLanguageId();
        $this->eurCurrencyId = $this->getCurrencyIdByIso('EUR');
        $this->usdCurrencyId = $this->getCurrencyIdByIso('USD');

        $this->browser = $this->createCustomSalesChannelBrowser([
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $this->deDeLanguageId],
            ],
            'currencyId' => $this->eurCurrencyId,
            'currencies' => [
                ['id' => $this->eurCurrencyId],
                ['id' => $this->usdCurrencyId],
            ],
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => $this->eurCurrencyId,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => self::EN_DOMAIN,
                ],
                [
                    'languageId' => $this->deDeLanguageId,
                    'currencyId' => $this->usdCurrencyId,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                    'url' => self::DE_DOMAIN,
                ],
            ],
        ]);
    }

    public function testResolvesLanguageAndCurrencyFromDomainHeader(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], $this->serverHeaders([
            PlatformRequest::HEADER_DOMAIN => self::DE_DOMAIN,
        ]));

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->deDeLanguageId, $this->resolvedLanguageId());
        static::assertSame($this->usdCurrencyId, $this->resolvedCurrencyId());
    }

    public function testBaseDomainIsDistinguishedFromSubPathDomain(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], $this->serverHeaders([
            PlatformRequest::HEADER_DOMAIN => self::EN_DOMAIN,
        ]));

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->resolvedLanguageId());
        static::assertSame($this->eurCurrencyId, $this->resolvedCurrencyId());
    }

    public function testResolvesLanguageWithTrailingSlash(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], $this->serverHeaders([
            PlatformRequest::HEADER_DOMAIN => self::DE_DOMAIN . '/',
        ]));

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->deDeLanguageId, $this->resolvedLanguageId());
        static::assertSame($this->usdCurrencyId, $this->resolvedCurrencyId());
    }

    public function testWithoutDomainHeaderFallsBackToSalesChannelDefault(): void
    {
        $this->browser->request('GET', '/store-api/context');

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->resolvedLanguageId());
        static::assertSame($this->eurCurrencyId, $this->resolvedCurrencyId());
    }

    public function testExplicitLanguageHeaderTakesPrecedenceOverDomain(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], $this->serverHeaders([
            PlatformRequest::HEADER_DOMAIN => self::DE_DOMAIN,
            PlatformRequest::HEADER_LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
        ]));

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        // explicit language wins, currency still resolved from the domain
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->resolvedLanguageId());
        static::assertSame($this->usdCurrencyId, $this->resolvedCurrencyId());
    }

    public function testExplicitCurrencyHeaderTakesPrecedenceOverDomain(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], $this->serverHeaders([
            PlatformRequest::HEADER_DOMAIN => self::DE_DOMAIN,
            PlatformRequest::HEADER_CURRENCY_ID => $this->eurCurrencyId,
        ]));

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        // language still resolved from the domain, explicit currency wins
        static::assertSame($this->deDeLanguageId, $this->resolvedLanguageId());
        static::assertSame($this->eurCurrencyId, $this->resolvedCurrencyId());
    }

    public function testUnknownDomainIsRejected(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], $this->serverHeaders([
            PlatformRequest::HEADER_DOMAIN => 'http://not-a-configured-domain.test',
        ]));

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(RoutingException::SALES_CHANNEL_DOMAIN_NOT_FOUND, $response['errors'][0]['code']);
    }

    /**
     * @param array<string, string> $headers header name => value
     *
     * @return array<string, string> server parameters for KernelBrowser::request()
     */
    private function serverHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($name))] = $value;
        }

        return $server;
    }

    private function resolvedLanguageId(): string
    {
        return $this->decodeContext()['languageIdChain'][0];
    }

    private function resolvedCurrencyId(): string
    {
        return $this->decodeContext()['currencyId'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeContext(): array
    {
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('context', $response);

        return $response['context'];
    }
}
