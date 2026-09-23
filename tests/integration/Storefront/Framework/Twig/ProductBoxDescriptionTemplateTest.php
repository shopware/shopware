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

    private SalesChannelProductEntity $product;

    protected function setUp(): void
    {
        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, productNumber: 'description-product'))
            ->price(gross: 10)
            ->description(self::DESCRIPTION)
            ->visibility()
            ->build();
        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $loadedProduct = static::getContainer()->get('sales_channel.product.repository')
            ->search(new Criteria([$ids->get('description-product')]), $this->salesChannelContext)
            ->first();
        static::assertInstanceOf(SalesChannelProductEntity::class, $loadedProduct);
        $this->product = $loadedProduct;
    }

    public function testProductBoxKeepsASpaceBetweenDescriptionParagraphs(): void
    {
        $html = $this->render('@Storefront/storefront/component/product/card/box-standard.html.twig', [
            'product' => $this->product,
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
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push(new Request());

        try {
            $html = $this->render('@Storefront/storefront/element/cms-element-product-description-reviews.html.twig', [
                'element' => ['data' => ['product' => $this->product]],
            ]);
        } finally {
            $requestStack->pop();
        }

        static::assertStringContainsString('sentence. Does', $html);
        static::assertStringNotContainsString('sentence.Does', $html);
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
