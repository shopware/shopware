<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

/**
 * @internal
 */
class SeoUrlPlaceholderHandlerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    use BasicTestDataBehaviour;
    use StorefrontSalesChannelTestHelper;

    private SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    protected function setUp(): void
    {
        /** @var Router|MockObject $router */
        $router = $this->createMock(Router::class);
        $router->method('generate')
            ->willReturnCallback(fn ($name, $params) => match ($name) {
                'frontend.detail.page' => '/detail/' . ($params['productId'] ?? ''),
                'frontend.navigation.page' => '/navigation/' . ($params['navigationId'] ?? ''),
                default => '',
            });

        $this->seoUrlPlaceholderHandler = new SeoUrlPlaceholderHandler(
            $this->getContainer()->get('request_stack'),
            $router,
            $this->getContainer()->get(Connection::class)
        );
    }

    public function testGenerateReplace(): void
    {
        $productId = Uuid::randomHex();
        $host = 'http://foo.text';
        $template = 'Test content with url %s.';

        $salesChannelContext = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'test storefront');

        $generated = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $productId]);

        $content = sprintf($template, $generated);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $salesChannelContext);

        $expectedUrl = $host . '/detail/' . $productId;
        $expected = sprintf($template, $expectedUrl);
        static::assertSame($expected, $actual);
    }

    public function testGenerateReplaceWithPrefixPath(): void
    {
        $productId = Uuid::randomHex();
        $host = 'http://foo.text:8000/de';
        $template = 'Test content with url %s.';

        $salesChannelContext = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'test storefront');

        $generated = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $productId]);

        $content = sprintf($template, $generated);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $salesChannelContext);

        $expectedUrl = $host . '/detail/' . $productId;
        $expected = sprintf($template, $expectedUrl);
        static::assertSame($expected, $actual);
    }

    public function testTwoUrls(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $host = 'http://foo.text:8000/de';
        $template = 'Test URL 1: %s and URL 2: %s';

        $salesChannelContext = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'test storefront');

        $generated1 = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $productId1]);
        $generated2 = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $productId2]);

        $content = sprintf($template, $generated1, $generated2);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $salesChannelContext);

        $expectedUrl1 = $host . '/detail/' . $productId1;
        $expectedUrl2 = $host . '/detail/' . $productId2;
        $expected = sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $actual);
    }

    public function testTwoEqualUrls(): void
    {
        $productId = Uuid::randomHex();
        $host = 'http://foo.text:8000/de';
        $template = 'Test URL 1: %s and URL 2: %s';

        $salesChannelContext = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'test storefront');

        $generated1 = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $productId]);
        $generated2 = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $productId]);

        $content = sprintf($template, $generated1, $generated2);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $salesChannelContext);

        $expectedUrl = $host . '/detail/' . $productId;
        $expected = sprintf($template, $expectedUrl, $expectedUrl);
        static::assertSame($expected, $actual);
    }

    public function testTwoDifferentEntities(): void
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $host = 'http://foo.text:8000/de';
        $template = 'Test URL 1: %s and URL 2: %s';

        $salesChannelContext = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'test storefront');

        $generated1 = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $productId]);
        $generated2 = $this->seoUrlPlaceholderHandler->generate('frontend.navigation.page', ['navigationId' => $categoryId]);

        $content = sprintf($template, $generated1, $generated2);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $salesChannelContext);

        $expectedUrl1 = $host . '/detail/' . $productId;
        $expectedUrl2 = $host . '/navigation/' . $categoryId;
        $expected = sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $actual);
    }

    public function testSeoReplacementSalesChannelDefaultAndOverride(): void
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test storefront');

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

        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('seo_url.repository');
        $repo->create($seoUrls, Context::createDefaultContext());

        $host = 'http://foo.text:8000/de';
        $template = 'SEO 1: %s and SEO 2: %s';

        $generated1 = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $productId]);
        $generated2 = $this->seoUrlPlaceholderHandler->generate('frontend.navigation.page', ['navigationId' => $categoryId]);

        $content = sprintf($template, $generated1, $generated2);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $salesChannelContext);

        $expectedUrl1 = $host . '/awesome-product';
        $expectedUrl2 = $host . '/cars-default';
        $expected = sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $actual);
    }
}
