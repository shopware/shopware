<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class ProductBoxDescriptionTemplateTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const DESCRIPTION = '<p>This is a short sentence. This is the second short sentence.</p><p>Does this improve the quality of the product description? I do not know.</p>';

    private SalesChannelContext $salesChannelContext;

    public function testProductBoxKeepsASpaceBetweenDescriptionParagraphs(): void
    {
        $html = $this->render('@Storefront/storefront/component/product/card/box-standard.html.twig', [
            'product' => $this->createProduct(self::DESCRIPTION),
            'layout' => 'standard',
        ]);

        static::assertSame(1, preg_match('/<p class="product-description[^"]*">\s*(?P<text>.*?)\s*<\/p>/s', $html, $matches), 'product description is missing');
        static::assertSame(
            'This is a short sentence. This is the second short sentence. Does this improve the quality of the product description? I do not know.',
            $matches['text']
        );
    }

    public function testDescriptionTabPreviewKeepsASpaceBetweenDescriptionParagraphs(): void
    {
        $html = $this->renderDescriptionTabPreview(self::DESCRIPTION);

        static::assertStringContainsString('sentence. Does', $html);
        static::assertStringContainsString('product-detail-tab-preview-more', $html);
    }

    public function testDescriptionTabPreviewIgnoresMarkupWhenDecidingToShowMore(): void
    {
        $html = $this->renderDescriptionTabPreview(
            '<p class="lead" style="font-weight: bold; color: #333333; margin-bottom: 16px;">Short description</p><p style="color: #333333;">with markup.</p>'
        );

        static::assertStringContainsString('Short description with markup.', $html);
        static::assertStringNotContainsString('product-detail-tab-preview-more', $html);
    }

    private function renderDescriptionTabPreview(string $description): string
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        try {
            return $this->render('@Storefront/storefront/element/cms-element-product-description-reviews.html.twig', [
                'element' => ['data' => ['product' => $this->createProduct($description)]],
            ]);
        } finally {
            $requestStack->pop();
        }
    }

    private function createProduct(string $description): SalesChannelProductEntity
    {
        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, productNumber: 'description-product'))
            ->price(gross: 10)
            ->description($description)
            ->visibility()
            ->build();
        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $loadedProduct = static::getContainer()->get('sales_channel.product.repository')
            ->search(new Criteria([$ids->get('description-product')]), $this->salesChannelContext)
            ->first();
        static::assertInstanceOf(SalesChannelProductEntity::class, $loadedProduct);

        return $loadedProduct;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function render(string $template, array $parameters): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig->render($template, [...$parameters, 'context' => $this->salesChannelContext]);
    }
}
