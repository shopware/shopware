<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const EN_DOMAIN = 'http://sw-domain-resolver-test.test';
    private const DE_DOMAIN = 'http://sw-domain-resolver-test.test/de';

    private KernelBrowser $browser;

    private string $deDeLanguageId;

    protected function setUp(): void
    {
        $this->deDeLanguageId = $this->getDeDeLanguageId();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $this->deDeLanguageId],
            ],
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => self::EN_DOMAIN,
                ],
                [
                    'languageId' => $this->deDeLanguageId,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                    'url' => self::DE_DOMAIN,
                ],
            ],
        ]);
    }

    public function testResolvesLanguageFromDomainHeader(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], [
            'HTTP_SW_DOMAIN' => self::DE_DOMAIN,
        ]);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->deDeLanguageId, $this->resolvedLanguageId());
    }

    public function testResolvesLanguageWithTrailingSlash(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], [
            'HTTP_SW_DOMAIN' => self::DE_DOMAIN . '/',
        ]);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->deDeLanguageId, $this->resolvedLanguageId());
    }

    public function testWithoutDomainHeaderFallsBackToSalesChannelDefault(): void
    {
        $this->browser->request('GET', '/store-api/context');

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->resolvedLanguageId());
    }

    public function testExplicitLanguageHeaderTakesPrecedenceOverDomain(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], [
            'HTTP_SW_DOMAIN' => self::DE_DOMAIN,
            'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_LANGUAGE_ID)) => Defaults::LANGUAGE_SYSTEM,
        ]);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->resolvedLanguageId());
    }

    public function testUnknownDomainIsRejected(): void
    {
        $this->browser->request('GET', '/store-api/context', [], [], [
            'HTTP_SW_DOMAIN' => 'http://not-a-configured-domain.test',
        ]);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(RoutingException::SALES_CHANNEL_DOMAIN_NOT_FOUND, $response['errors'][0]['code']);
    }

    private function resolvedLanguageId(): string
    {
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('context', $response);
        static::assertArrayHasKey('languageIdChain', $response['context']);

        return $response['context']['languageIdChain'][0];
    }
}
