<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SeoUrlPlaceholderHandler::class)]
class SeoUrlPlaceholderHandlerTest extends TestCase
{
    private MockObject&Connection $connection;

    private MockObject&SalesChannelContext $salesChannelContext;

    private SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->salesChannelContext->expects(static::once())->method('getContext')->willReturn($context);
        $this->salesChannelContext->expects(static::once())->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $this->seoUrlPlaceholderHandler = new SeoUrlPlaceholderHandler(
            $this->createMock(RequestStack::class),
            $this->createMock(Router::class),
            $this->connection
        );
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
        $this->connection->expects(static::once())->method('executeQuery')->willReturn($this->createMock(Result::class));

        static::assertSame($expected, $this->seoUrlPlaceholderHandler->replace($content, $host, $this->salesChannelContext));
    }

    public function testSeoReplacementSalesChannelDefaultAndOverride(): void
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $result = $this->createMock(Result::class);
        $result->expects(static::once())->method('fetchAllAssociative')->willReturn([
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
        $expected = sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $actual);
    }
}
