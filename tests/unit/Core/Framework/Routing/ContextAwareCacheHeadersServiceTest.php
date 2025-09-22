<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Routing\ContextAwareCacheHeadersService;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ContextAwareCacheHeadersService::class)]
class ContextAwareCacheHeadersServiceTest extends TestCase
{
    private ContextAwareCacheHeadersService $contextAwareCacheService;

    protected function setUp(): void
    {
        $entityCacheKeyGenerator = new EntityCacheKeyGenerator();
        $this->contextAwareCacheService = new ContextAwareCacheHeadersService(
            $entityCacheKeyGenerator,
            new CacheRelevantRulesResolver(new ExtensionDispatcher(new EventDispatcher()))
        );
    }

    /**
     * @param array<string, string> $existingHeaders
     * @param array<string> $expectedVaryHeaders
     */
    #[DataProvider('provideTestCases')]
    public function testAddContextHeaders(array $existingHeaders, array $expectedVaryHeaders, SalesChannelContext $context): void
    {
        $request = new Request();
        $response = new Response();

        // Set existing headers if provided
        foreach ($existingHeaders as $name => $value) {
            $response->headers->set($name, $value);
        }

        $this->contextAwareCacheService->addContextHeaders($request, $response, $context);

        // Verify headers are set
        static::assertSame($context->getLanguageId(), $response->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        static::assertSame($context->getCurrencyId(), $response->headers->get(PlatformRequest::HEADER_CURRENCY_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_HASH));

        // Verify Vary header contains expected values
        $varyHeader = $response->headers->get('Vary');
        static::assertNotNull($varyHeader);
        $varyHeaders = array_map(fn (string $v) => trim($v), explode(',', $varyHeader));
        foreach ($expectedVaryHeaders as $expectedHeader) {
            static::assertContains($expectedHeader, $varyHeaders);
        }
    }

    public static function provideTestCases(): \Generator
    {
        yield 'basic headers' => [
            'existingHeaders' => [],
            'expectedVaryHeaders' => [
                PlatformRequest::HEADER_LANGUAGE_ID,
                PlatformRequest::HEADER_CURRENCY_ID,
                PlatformRequest::HEADER_CONTEXT_HASH,
            ],
            'context' => self::createSalesChannelContext(),
        ];

        yield 'with existing vary header' => [
            'existingHeaders' => ['Vary' => 'Accept, Accept-Language'],
            'expectedVaryHeaders' => [
                'Accept',
                'Accept-Language',
                PlatformRequest::HEADER_LANGUAGE_ID,
                PlatformRequest::HEADER_CURRENCY_ID,
                PlatformRequest::HEADER_CONTEXT_HASH,
            ],
            'context' => self::createSalesChannelContext(),
        ];

        yield 'with custom language and currency' => [
            'existingHeaders' => [],
            'expectedVaryHeaders' => [
                PlatformRequest::HEADER_LANGUAGE_ID,
                PlatformRequest::HEADER_CURRENCY_ID,
                PlatformRequest::HEADER_CONTEXT_HASH,
            ],
            'context' => self::createSalesChannelContext('custom-language-id', 'custom-currency-id'),
        ];

        yield 'with custom rules' => [
            'existingHeaders' => [],
            'expectedVaryHeaders' => [
                PlatformRequest::HEADER_LANGUAGE_ID,
                PlatformRequest::HEADER_CURRENCY_ID,
                PlatformRequest::HEADER_CONTEXT_HASH,
            ],
            'context' => self::createSalesChannelContext(
                ruleIds: ['rule-1', 'rule-2'],
            ),
        ];
    }

    /**
     * @param array<string> $ruleIds
     */
    private static function createSalesChannelContext(string $languageId = Defaults::LANGUAGE_SYSTEM, string $currencyId = Defaults::CURRENCY, array $ruleIds = []): SalesChannelContext
    {
        $baseContext = new Context(
            new SystemSource(),
            $ruleIds,
            $currencyId,
            [$languageId]
        );

        $currency = new CurrencyEntity();
        $currency->setId($currencyId);

        $languageInfo = Generator::createLanguageInfo($languageId, 'Test Language');

        return Generator::generateSalesChannelContext(
            baseContext: $baseContext,
            currency: $currency,
            areaRuleIds: [RuleAreas::PRODUCT_AREA => $ruleIds],
            languageInfo: $languageInfo
        );
    }
}
