<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlPlaceholderHandler::class)]
class SeoUrlPlaceholderHandlerTest extends TestCase
{
    private Connection&Stub $connection;

    private SalesChannelContext $salesChannelContext;

    private SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn(static::createStub(AbstractPlatform::class));

        $this->salesChannelContext = Generator::generateSalesChannelContext();

        $this->seoUrlPlaceholderHandler = $this->createHandler();
    }

    /**
     * @return iterable<string, array<string, string>>
     */
    public static function replaceDataProvider(): iterable
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        yield 'one url' => [
            'host' => 'http://foo.text',
            'content' => 'Test content with url ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $productId1 . '#.',
            'expected' => 'Test content with url http://foo.text/detail/' . $productId1 . '.',
        ];

        yield 'url with prefix path' => [
            'host' => 'http://foo.text:8000/de',
            'content' => 'Test content with url ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $productId1 . '#.',
            'expected' => 'Test content with url http://foo.text:8000/de/detail/' . $productId1 . '.',
        ];

        yield 'two urls' => [
            'host' => 'http://foo.text:8000/de',
            'content' => 'Test URL 1: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $productId1 . '# and URL 2: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $productId2 . '#',
            'expected' => 'Test URL 1: http://foo.text:8000/de/detail/' . $productId1 . ' and URL 2: http://foo.text:8000/de/detail/' . $productId2,
        ];

        yield 'two equal urls' => [
            'host' => 'http://foo.text:8000/de',
            'content' => 'Test URL 1: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $productId1 . '# and URL 2: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $productId1 . '#',
            'expected' => 'Test URL 1: http://foo.text:8000/de/detail/' . $productId1 . ' and URL 2: http://foo.text:8000/de/detail/' . $productId1,
        ];

        yield 'two different entities' => [
            'host' => 'http://foo.text:8000/de',
            'content' => 'Test URL 1: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $productId1 . '# and URL 2: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/navigation/' . $categoryId . '#',
            'expected' => 'Test URL 1: http://foo.text:8000/de/detail/' . $productId1 . ' and URL 2: http://foo.text:8000/de/navigation/' . $categoryId,
        ];
    }

    #[DataProvider('replaceDataProvider')]
    public function testReplace(string $host, string $content, string $expected): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(static::createStub(AbstractPlatform::class));
        $connection->expects($this->once())->method('executeQuery')->willReturn(static::createStub(Result::class));

        static::assertSame($expected, $this->createHandler($connection)->replace($content, $host, $this->salesChannelContext));
    }

    public function testSeoReplacementSalesChannelDefaultAndOverride(): void
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'seo_path_info' => 'awesome-product',
                'path_info' => '/detail/' . $productId,
                'sales_channel_id' => TestDefaults::SALES_CHANNEL,
            ],
            [
                'seo_path_info' => 'cars-default',
                'path_info' => '/navigation/' . $categoryId,
                'sales_channel_id' => TestDefaults::SALES_CHANNEL,
            ],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $host = 'http://foo.text:8000/de';
        $template = 'SEO 1: %s and SEO 2: %s';

        $content = 'SEO 1: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $productId . '# and SEO 2: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/navigation/' . $categoryId . '#';
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $this->salesChannelContext);

        $expectedUrl1 = $host . '/awesome-product';
        $expectedUrl2 = $host . '/cars-default';
        $expected = \sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $actual);
    }

    public function testReplaceWithoutPlaceholderReturnsContentUnchangedAndSkipsDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(static::createStub(AbstractPlatform::class));
        // no placeholder present -> the regex/DB lookup must be skipped entirely (e.g. JSON/AJAX responses)
        $connection->expects($this->never())->method('executeQuery');

        $content = 'Plain response body without any SEO placeholder, e.g. a JSON API payload.';

        static::assertSame(
            $content,
            $this->createHandler($connection)->replace($content, 'http://foo.text', $this->salesChannelContext)
        );
    }

    public function testReplacePrependsExternalStorefrontDomainForHeadlessSalesChannel(): void
    {
        $productId = Uuid::randomHex();

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'seo_path_info' => 'product/' . $productId,
                'path_info' => '/store-api/product/' . $productId,
                'sales_channel_id' => TestDefaults::SALES_CHANNEL,
            ],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $context = $this->createHeadlessSalesChannelContext(true);

        $content = 'SEO: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/store-api/product/' . $productId . '#';
        $actual = $this->seoUrlPlaceholderHandler->replace($content, 'https://my-storefront.com', $context);

        static::assertSame('SEO: https://foo.bar/product/' . $productId, $actual);
    }

    public function testReplaceKeepsRelativePathForHeadlessSalesChannelWithoutExternalStorefrontDomain(): void
    {
        $productId = Uuid::randomHex();

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'seo_path_info' => 'product/' . $productId,
                'path_info' => '/store-api/product/' . $productId,
                'sales_channel_id' => TestDefaults::SALES_CHANNEL,
            ],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $context = $this->createHeadlessSalesChannelContext(false);

        $content = 'SEO: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/store-api/product/' . $productId . '#';
        $actual = $this->seoUrlPlaceholderHandler->replace($content, 'https://my-storefront.com', $context);

        static::assertSame('SEO: product/' . $productId, $actual);
    }

    public function testReplaceKeepsRelativePathForHeadlessSalesChannelWhenExternalStorefrontDomainLanguageDiffers(): void
    {
        $productId = Uuid::randomHex();

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'seo_path_info' => 'product/' . $productId,
                'path_info' => '/store-api/product/' . $productId,
                'sales_channel_id' => TestDefaults::SALES_CHANNEL,
            ],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $context = $this->createHeadlessSalesChannelContext(true, Uuid::randomHex());

        $content = 'SEO: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/store-api/product/' . $productId . '#';
        $actual = $this->seoUrlPlaceholderHandler->replace($content, 'https://my-storefront.com', $context);

        static::assertSame('SEO: product/' . $productId, $actual);
    }

    /**
     * @return iterable<string, array{generatedPath: string, server: array<string, string>, expected: string}>
     */
    public static function generateDataProvider(): iterable
    {
        yield 'no base url' => [
            'generatedPath' => '/navigation/abc',
            'server' => [],
            'expected' => '/navigation/abc',
        ];

        yield 'script name in url' => [
            'generatedPath' => '/index.php/navigation/abc',
            'server' => ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/index.php', 'PHP_SELF' => '/index.php/navigation/abc'],
            'expected' => '/navigation/abc',
        ];

        yield 'sub directory' => [
            'generatedPath' => '/subdir/navigation/abc',
            'server' => ['SCRIPT_NAME' => '/subdir/index.php', 'SCRIPT_FILENAME' => '/subdir/index.php', 'PHP_SELF' => '/subdir/navigation/abc'],
            'expected' => '/navigation/abc',
        ];

        yield 'sub directory and script name in url' => [
            'generatedPath' => '/subdir/index.php/navigation/abc',
            'server' => ['SCRIPT_NAME' => '/subdir/index.php', 'SCRIPT_FILENAME' => '/subdir/index.php', 'PHP_SELF' => '/subdir/index.php/navigation/abc'],
            'expected' => '/navigation/abc',
        ];

        yield 'generated path without the base url of the request' => [
            'generatedPath' => '/navigation/abc',
            'server' => ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/index.php', 'PHP_SELF' => '/index.php/navigation/abc'],
            'expected' => '/navigation/abc',
        ];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('generateDataProvider')]
    public function testGenerateRemovesBaseUrlOfRequest(string $generatedPath, array $server, string $expected): void
    {
        $router = static::createStub(Router::class);
        $router->method('generate')->willReturn($generatedPath);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create($server['PHP_SELF'] ?? '/navigation/abc', server: $server));

        $handler = new SeoUrlPlaceholderHandler($requestStack, $router, $this->connection);

        static::assertSame(
            SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . $expected . '#',
            $handler->generate('frontend.navigation.page', ['navigationId' => 'abc'])
        );
    }

    public function testGenerateWithoutRequest(): void
    {
        $router = static::createStub(Router::class);
        $router->method('generate')->willReturn('/navigation/abc');

        $handler = new SeoUrlPlaceholderHandler(new RequestStack(), $router, $this->connection);

        static::assertSame(
            SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/navigation/abc#',
            $handler->generate('frontend.navigation.page')
        );
    }

    private function createHeadlessSalesChannelContext(bool $externalStorefront, ?string $domainLanguageId = null): SalesChannelContext
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setTypeId(Defaults::SALES_CHANNEL_TYPE_API);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://foo.bar');
        $domain->setIsExternalStorefront($externalStorefront);
        $domain->setLanguageId($domainLanguageId ?? $context->getLanguageId());

        $context->getSalesChannel()->setDomains(new SalesChannelDomainCollection([$domain]));

        return $context;
    }

    private function createHandler(?Connection $connection = null): SeoUrlPlaceholderHandler
    {
        return new SeoUrlPlaceholderHandler(
            static::createStub(RequestStack::class),
            static::createStub(Router::class),
            $connection ?? $this->connection
        );
    }
}
