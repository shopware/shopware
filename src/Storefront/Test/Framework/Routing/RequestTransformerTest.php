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
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;

class RequestTransformerTest extends TestCase
{
    use IntegrationTestBehaviour;
    public const LOCALE_DE_DE_ISO = 'de_DE';
    public const LOCALE_EN_GB_ISO = 'en_GB';

    /**
     * @var RequestTransformer
     */
    private $requestBuilder;

    protected function setUp(): void
    {
        $this->requestBuilder = new RequestTransformer($this->getContainer()->get(Connection::class));
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

        /** @var ExpectedRequest $expectedRequest */
        foreach ($requests as $expectedRequest) {
            $request = Request::create($expectedRequest->url);

            $resolved = $this->requestBuilder->transform($request);

            static::assertSame($expectedRequest->salesChannelId, $resolved->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

            static::assertSame($expectedRequest->domainId, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID));
            static::assertSame($expectedRequest->isStorefrontRequest, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
            static::assertSame($expectedRequest->locale, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE));
            static::assertSame($expectedRequest->currency, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
            static::assertSame($expectedRequest->snippetSetId, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID));

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

        return [
            'single' => [
                [$this->getGermanSalesChannel($germanId, $gerDomainId, 'http://german.test')],
                [
                    new ExpectedRequest('http://german.test', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://german.test/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://german.test/foobar', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                ],
            ],
            'two' => [
                [
                    $this->getGermanSalesChannel($germanId, $gerDomainId, 'http://german.test'),
                    $this->getEnglishSalesChannel($englishId, $ukDomainId, 'http://english.test'),
                ],
                [
                    new ExpectedRequest('http://german.test', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://german.test/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://german.test/foobar', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),

                    new ExpectedRequest('http://english.test', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://english.test/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://english.test/foobar', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                ],
            ],
            'single-with-ger-and-uk-domain' => [
                [
                    $this->getSalesChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://german.test', $ukDomainId, 'http://english.test'),
                ],
                [
                    new ExpectedRequest('http://german.test', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://german.test/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://german.test/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),

                    new ExpectedRequest('http://english.test', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://english.test/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://english.test/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                ],
            ],
            'two-domains-same-host-different-path' => [
                [
                    $this->getSalesChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test/en'),
                ],
                [
                    new ExpectedRequest('http://saleschannel.test/de', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/de/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/de/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),

                    new ExpectedRequest('http://saleschannel.test/en', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://saleschannel.test/en/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://saleschannel.test/en/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                ],
            ],
            'two-domains-same-host-extended-path' => [
                [
                    $this->getSalesChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test'),
                ],
                [
                    new ExpectedRequest('http://saleschannel.test/de', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/de/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),
                    new ExpectedRequest('http://saleschannel.test/de/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM_DE, $snippetSetDE),

                    new ExpectedRequest('http://saleschannel.test', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://saleschannel.test/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                    new ExpectedRequest('http://saleschannel.test/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, $snippetSetEN),
                ],
            ],
            'inactive' => [
                [
                    $this->getInactiveSalesChannel($germanId, $gerDomainId, 'http://inactive.test'),
                ],
                [
                    new ExpectedRequest('http://inactive.test', null, null, null, null, null, null, null),
                    new ExpectedRequest('http://inactive.test/', null, null, null, null, null, null, null),
                    new ExpectedRequest('http://inactive.test/foobar', null, null, null, null, null, null, null),
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
                ['id' => Defaults::LANGUAGE_SYSTEM_DE],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => Defaults::LANGUAGE_SYSTEM_DE,
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
                ['id' => Defaults::LANGUAGE_SYSTEM_DE],
            ],
            'domains' => [
                [
                    'id' => $gerDomainId,
                    'url' => $gerUrl,
                    'languageId' => Defaults::LANGUAGE_SYSTEM_DE,
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
                ['id' => Defaults::LANGUAGE_SYSTEM_DE],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => Defaults::LANGUAGE_SYSTEM_DE,
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

    /** @var string */
    public $domainId;

    /** @var string */
    public $salesChannelId;

    /** @var bool */
    public $isStorefrontRequest;

    /** @var string */
    public $locale;

    /** @var string */
    public $currency;

    /** @var string */
    public $language;

    /** @var string */
    public $snippetSetId;

    public function __construct(
        string $url,
        ?string $domainId,
        ?string $salesChannelId,
        ?bool $isStorefrontRequest,
        ?string $locale,
        ?string $currency,
        ?string $language,
        ?string $snippetSetId
    ) {
        $this->url = $url;
        $this->domainId = $domainId;
        $this->salesChannelId = $salesChannelId;
        $this->isStorefrontRequest = $isStorefrontRequest;
        $this->locale = $locale;
        $this->currency = $currency;
        $this->language = $language;
        $this->snippetSetId = $snippetSetId;
    }
}
