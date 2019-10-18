<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SeoUrlReplacerServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    use BasicTestDataBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlReplacer;

    public function setUp(): void
    {
        $this->seoUrlReplacer = $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);
    }

    public function testGenerateReplace(): void
    {
        $productId = Uuid::randomHex();
        $domain = 'foo.text';
        $template = 'Test content with url %s.';
        $request = $this->getRequest($domain);

        $generated = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $productId]);
        static::assertStringNotContainsString($domain, $generated);

        $response = new Response(sprintf($template, $generated));
        $this->seoUrlReplacer->replacePlaceholder($request, $response);

        $expectedUrl = 'http://' . $domain . '/detail/' . $productId;
        $expected = sprintf($template, $expectedUrl);
        static::assertSame($expected, $response->getContent());
    }

    public function testGenerateReplaceWithPrefixPath(): void
    {
        $productId = Uuid::randomHex();
        $domain = 'foo.text:8000';
        $prefixPath = '/de';
        $template = 'Test content with url %s.';
        $request = $this->getRequest($domain, Defaults::SALES_CHANNEL, Defaults::LANGUAGE_SYSTEM, $prefixPath);

        $generated = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $productId]);
        static::assertStringNotContainsString($domain . $prefixPath, $generated);

        $response = new Response(sprintf($template, $generated));
        $this->seoUrlReplacer->replacePlaceholder($request, $response);

        $expectedUrl = 'http://' . $domain . $prefixPath . '/detail/' . $productId;
        $expected = sprintf($template, $expectedUrl);
        static::assertSame($expected, $response->getContent());
    }

    public function testTwoUrls(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $domain = 'foo.text:8000';
        $prefixPath = '/de';
        $template = 'Test URL 1: %s and URL 2: %s';
        $request = $this->getRequest($domain, Defaults::SALES_CHANNEL, Defaults::LANGUAGE_SYSTEM, $prefixPath);

        $generated1 = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $productId1]);
        $generated2 = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $productId2]);
        static::assertStringNotContainsString($domain . $prefixPath, $generated1);
        static::assertStringNotContainsString($domain . $prefixPath, $generated2);

        $response = new Response(sprintf($template, $generated1, $generated2));
        $this->seoUrlReplacer->replacePlaceholder($request, $response);

        $expectedUrl1 = 'http://' . $domain . $prefixPath . '/detail/' . $productId1;
        $expectedUrl2 = 'http://' . $domain . $prefixPath . '/detail/' . $productId2;
        $expected = sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $response->getContent());
    }

    public function testTwoEqualUrls(): void
    {
        $productId = Uuid::randomHex();
        $domain = 'foo.text:8000';
        $prefixPath = '/de';
        $template = 'Test URL 1: %s and URL 2: %s';
        $request = $this->getRequest($domain, Defaults::SALES_CHANNEL, Defaults::LANGUAGE_SYSTEM, $prefixPath);

        $generated1 = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $productId]);
        $generated2 = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $productId]);
        static::assertStringNotContainsString($domain . $prefixPath, $generated1);
        static::assertStringNotContainsString($domain . $prefixPath, $generated2);

        $response = new Response(sprintf($template, $generated1, $generated2));
        $this->seoUrlReplacer->replacePlaceholder($request, $response);

        $expectedUrl = 'http://' . $domain . $prefixPath . '/detail/' . $productId;
        $expected = sprintf($template, $expectedUrl, $expectedUrl);
        static::assertSame($expected, $response->getContent());
    }

    public function testTwoDifferentEntities(): void
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $domain = 'foo.text:8000';
        $prefixPath = '/de';
        $template = 'Test URL 1: %s and URL 2: %s';
        $request = $this->getRequest($domain, Defaults::SALES_CHANNEL, Defaults::LANGUAGE_SYSTEM, $prefixPath);

        $generated1 = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $productId]);
        $generated2 = $this->seoUrlReplacer->generate('frontend.navigation.page', ['navigationId' => $categoryId]);
        static::assertStringNotContainsString($domain . $prefixPath, $generated1);
        static::assertStringNotContainsString($domain . $prefixPath, $generated2);

        $response = new Response(sprintf($template, $generated1, $generated2));
        $this->seoUrlReplacer->replacePlaceholder($request, $response);

        $expectedUrl1 = 'http://' . $domain . $prefixPath . '/detail/' . $productId;
        $expectedUrl2 = 'http://' . $domain . $prefixPath . '/navigation/' . $categoryId;
        $expected = sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $response->getContent());
    }

    public function testSeoReplacementSalesChannelDefaultAndOverride(): void
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test storefront');

        $seoUrls = [
            [
                'languageId' => $languageId,
                'salesChannelId' => $salesChannelId,
                'foreignKey' => $productId,
                'routeName' => 'test',
                'pathInfo' => '/detail/' . $productId,
                'seoPathInfo' => 'awesome-product',
                'isCanonical' => true,
            ],
            [
                'languageId' => $languageId,
                'salesChannelId' => null,
                'foreignKey' => $productId,
                'routeName' => 'test',
                'pathInfo' => '/detail/' . $productId,
                'seoPathInfo' => 'product-default',
                'isCanonical' => true,
            ],
            [
                'languageId' => $languageId,
                'salesChannelId' => null,
                'foreignKey' => $categoryId,
                'routeName' => 'test',
                'pathInfo' => '/navigation/' . $categoryId,
                'seoPathInfo' => 'cars-default',
                'isCanonical' => true,
            ],
        ];

        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('seo_url.repository');
        $repo->create($seoUrls, Context::createDefaultContext());

        $domain = 'foo.text:8000';
        $prefixPath = '/de';
        $template = 'SEO 1: %s and SEO 2: %s';
        $request = $this->getRequest($domain, $salesChannelId, $languageId, $prefixPath);

        $generated1 = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $productId]);
        $generated2 = $this->seoUrlReplacer->generate('frontend.navigation.page', ['navigationId' => $categoryId]);
        static::assertStringNotContainsString($domain . $prefixPath, $generated1);
        static::assertStringNotContainsString($domain . $prefixPath, $generated2);

        $response = new Response(sprintf($template, $generated1, $generated2));
        $this->seoUrlReplacer->replacePlaceholder($request, $response);

        $expectedUrl1 = 'http://' . $domain . $prefixPath . '/awesome-product';
        $expectedUrl2 = 'http://' . $domain . $prefixPath . '/cars-default';
        $expected = sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $response->getContent());
    }

    private function getRequest(
        string $domain,
        string $salesChannelId = Defaults::SALES_CHANNEL,
        string $languageId = Defaults::LANGUAGE_SYSTEM,
        string $prefixPath = ''
    ): Request {
        $attributes = [
            RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL => 'http://' . $domain,
            RequestTransformer::SALES_CHANNEL_BASE_URL => $prefixPath,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $salesChannelId,
        ];
        $server = [
            'HTTP_' . mb_strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID)) => $languageId,
        ];

        return new Request([], [], $attributes, [], [], $server);
    }
}
