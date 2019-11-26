<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;

class RequestTransformerTest extends TestCase
{
    use IntegrationTestBehaviour;
    public const LOCALE_DE_DE_ISO = 'de-DE';
    public const LOCALE_EN_GB_ISO = 'en-GB';

    /**
     * @var RequestTransformer
     */
    private $requestBuilder;

    /**
     * @var string
     */
    private $deLanguageId;

    protected function setUp(): void
    {
        $this->requestBuilder = new RequestTransformer(
            new \Shopware\Core\Framework\Routing\RequestTransformer(),
            $this->getContainer()->get(Connection::class)
        );

        $this->deLanguageId = $this->getDeDeLanguageId();
    }

    /**
     * @dataProvider domainProvider
     *
     * @param array[]           $salesChannels
     * @param ExpectedRequest[] $requests
     */
    public function testDomainResolving(array $salesChannels, array $requests): void
    {
        $this->createSalesChannels($salesChannels);

        foreach ($requests as $expectedRequest) {
            if ($expectedRequest->exception) {
                static::expectException($expectedRequest->exception);
            }

            $request = Request::create($expectedRequest->url);

            $resolved = $this->requestBuilder->transform($request);

            static::assertSame($expectedRequest->salesChannelId, $resolved->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

            static::assertSame($expectedRequest->domainId, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID));
            static::assertSame($expectedRequest->isStorefrontRequest, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
            static::assertSame($expectedRequest->locale, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE));
            static::assertSame($expectedRequest->currency, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
            static::assertSame($expectedRequest->snippetSetId, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID));
            static::assertSame($expectedRequest->baseUrl, $resolved->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL), $expectedRequest->url);
            static::assertSame($expectedRequest->resolvedUrl, $resolved->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI));
            static::assertSame($expectedRequest->language, $resolved->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        }
    }

    public function domainProvider(): array
    {
        $germanId = Uuid::randomHex();
        $englishId = Uuid::randomHex();
        $gerUkId = Uuid::randomHex();

        $gerDomainId = Uuid::randomHex();
        $ukDomainId = Uuid::randomHex();

        $snippetSetDE = $this->getSnippetSetIdForLocale(self::LOCALE_DE_DE_ISO);
        $snippetSetEN = $this->getSnippetSetIdForLocale(self::LOCALE_EN_GB_ISO);
        $this->deLanguageId = $this->getDeDeLanguageId();

        return [
            'single' => [
                [$this->getGermanSalesChannel($germanId, $gerDomainId, 'http://german.test')],
                [
                    new ExpectedRequest('http://german.test', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://german.test/', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://german.test/foobar', '', '/foobar', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                ],
            ],
            'two' => [
                [
                    $this->getGermanSalesChannel($germanId, $gerDomainId, 'http://german.test'),
                    $this->getEnglishSalesChannel($englishId, $ukDomainId, 'http://english.test'),
                ],
                [
                    new ExpectedRequest('http://german.test', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://german.test/', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://german.test/foobar', '', '/foobar', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),

                    new ExpectedRequest('http://english.test', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://english.test/', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://english.test/foobar', '', '/foobar', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),

                    new ExpectedRequest('http://english.test/navigation/1', '', '/navigation/1', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://german.test/navigation/1', '', '/navigation/1', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                ],
            ],
            'single-with-ger-and-uk-domain' => [
                [
                    $this->getSalesChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://german.test', $ukDomainId, 'http://english.test'),
                ],
                [
                    new ExpectedRequest('http://german.test', '', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://german.test/', '', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://german.test/foobar', '', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),

                    new ExpectedRequest('http://english.test', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://english.test/', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://english.test/foobar', '', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                ],
            ],
            'single-with-ger-and-uk-domain-with-port' => [
                [
                    $this->getSalesChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://base.test:1337', $ukDomainId, 'http://base.test:31337'),
                ],
                [
                    new ExpectedRequest('http://base.test:1337', '', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://base.test:1337/', '', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://base.test:1337/foobar', '', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),

                    new ExpectedRequest('http://base.test:31337', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://base.test:31337/', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://base.test:31337/foobar', '', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                ],
            ],

            'single-with-ger-and-uk-domain-with-same-port-different-path' => [
                [
                    $this->getSalesChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://base.test:1337/foo', $ukDomainId, 'http://base.test:1337/bar'),
                ],
                [
                    new ExpectedRequest('http://base.test:1337/foo', '/foo', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://base.test:1337/foo/', '/foo', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://base.test:1337/foo/foobar', '/foo', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),

                    new ExpectedRequest('http://base.test:1337/bar', '/bar', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://base.test:1337/bar/', '/bar', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://base.test:1337/bar/foobar', '/bar', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                ],
            ],

            'two-domains-same-host-different-path' => [
                [
                    $this->getSalesChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test/en'),
                ],
                [
                    new ExpectedRequest('http://saleschannel.test/de', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/de/', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/de/foobar', '/de', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),

                    new ExpectedRequest('http://saleschannel.test/en', '/en', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://saleschannel.test/en/', '/en', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://saleschannel.test/en/foobar', '/en', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),

                    new ExpectedRequest('http://saleschannel.test/de/navigation/1', '/de', '/navigation/1', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/en/navigation/1', '/en', '/navigation/1', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),

                    new ExpectedRequest('http://saleschannel.test/de/de/navigation/1', '/de', '/de/navigation/1', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/en/en/navigation/1', '/en', '/en/navigation/1', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                ],
            ],
            'two-domains-same-host-extended-path' => [
                [
                    $this->getSalesChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test'),
                ],
                [
                    new ExpectedRequest('http://saleschannel.test/de', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/de/', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/de/foobar', '/de', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),

                    new ExpectedRequest('http://saleschannel.test', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://saleschannel.test/', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://saleschannel.test/foobar', '', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                ],
            ],
            'inactive' => [
                [
                    $this->getInactiveSalesChannel($germanId, $gerDomainId, 'http://inactive.test'),
                ],
                [
                    new ExpectedRequest('http://inactive.test', null, null, null, null, null, null, null, null, null, SalesChannelMappingException::class),
                    new ExpectedRequest('http://inactive.test/', null, null, null, null, null, null, null, null, null, SalesChannelMappingException::class),
                    new ExpectedRequest('http://inactive.test/foobar', null, null, null, null, null, null, null, null, null, SalesChannelMappingException::class),
                ],
            ],
            'punycode' => [
                [
                    $this->getGermanSalesChannel($germanId, $gerDomainId, 'http://würmer.test'),
                ],
                [
                    new ExpectedRequest('http://xn--wrmer-kva.test', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://xn--wrmer-kva.test/', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                    new ExpectedRequest('http://xn--wrmer-kva.test/foobar', '', '/foobar', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, $this->deLanguageId, $snippetSetDE),
                ],
            ],
        ];
    }

    private function getEnglishSalesChannel(string $salesChannelId, string $domainId, string $url): array
    {
        return [
            'id' => $salesChannelId,
            'name' => 'english',
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale(self::LOCALE_EN_GB_ISO),
                ],
            ],
        ];
    }

    private function getGermanSalesChannel(string $salesChannelId, string $domainId, string $url): array
    {
        return [
            'id' => $salesChannelId,
            'name' => 'german',
            'languages' => [
                ['id' => $this->deLanguageId],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => $this->deLanguageId,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale(self::LOCALE_DE_DE_ISO),
                ],
            ],
        ];
    }

    private function getSalesChannelWithGerAndUkDomain(string $salesChannelId, string $gerDomainId, string $gerUrl, string $ukDomainId, string $ukUrl): array
    {
        return [
            'id' => $salesChannelId,
            'name' => 'english',
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $this->deLanguageId],
            ],
            'domains' => [
                [
                    'id' => $gerDomainId,
                    'url' => $gerUrl,
                    'languageId' => $this->deLanguageId,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale(self::LOCALE_DE_DE_ISO),
                ],
                [
                    'id' => $ukDomainId,
                    'url' => $ukUrl,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale(self::LOCALE_EN_GB_ISO),
                ],
            ],
        ];
    }

    private function getInactiveSalesChannel(string $salesChannelId, string $domainId, string $url): array
    {
        return [
            'id' => $salesChannelId,
            'name' => 'inactive sales channel',
            'active' => false,
            'languages' => [
                ['id' => $this->deLanguageId],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => $this->deLanguageId,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale(self::LOCALE_DE_DE_ISO),
                ],
            ],
        ];
    }

    private function createSalesChannels($salesChannels): EntityWrittenContainerEvent
    {
        $salesChannels = array_map(function ($salesChannelData) {
            $defaults = [
                'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale(self::LOCALE_EN_GB_ISO),
                'currencyId' => Defaults::CURRENCY,
                'currencyVersionId' => Defaults::LIVE_VERSION,
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'paymentMethodVersionId' => Defaults::LIVE_VERSION,
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingMethodVersionId' => Defaults::LIVE_VERSION,
                'navigationCategoryId' => $this->getValidCategoryId(),
                'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
                'countryId' => $this->getValidCountryId(),
                'countryVersionId' => Defaults::LIVE_VERSION,
                'currencies' => [['id' => Defaults::CURRENCY]],
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
                'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
                'countries' => [['id' => $this->getValidCountryId()]],
                'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            ];

            return array_merge_recursive($defaults, $salesChannelData);
        }, $salesChannels);

        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        return $salesChannelRepository->create($salesChannels, Context::createDefaultContext());
    }
}

class ExpectedRequest
{
    /** @var string */
    public $url;

    /** @var string|null */
    public $baseUrl;

    /** @var string|null */
    public $domainId;

    /** @var string|null */
    public $salesChannelId;

    /** @var bool|null */
    public $isStorefrontRequest;

    /** @var string|null */
    public $locale;

    /** @var string|null */
    public $currency;

    /** @var string|null */
    public $language;

    /** @var string|null */
    public $snippetSetId;

    /** @var string|null */
    public $exception;

    /** @var string|null */
    public $resolvedUrl;

    public function __construct(
        string $url,
        ?string $baseUrl,
        ?string $resolvedUrl,
        ?string $domainId,
        ?string $salesChannelId,
        ?bool $isStorefrontRequest,
        ?string $locale,
        ?string $currency,
        ?string $language,
        ?string $snippetSetId,
        ?string $exception = null
    ) {
        $this->url = $url;
        $this->domainId = $domainId;
        $this->salesChannelId = $salesChannelId;
        $this->isStorefrontRequest = $isStorefrontRequest;
        $this->locale = $locale;
        $this->currency = $currency;
        $this->language = $language;
        $this->snippetSetId = $snippetSetId;
        $this->baseUrl = $baseUrl;
        $this->exception = $exception;
        $this->resolvedUrl = $resolvedUrl;
    }
}
