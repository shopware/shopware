<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class ZugferdRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private ZugferdRenderer $renderer;

    private CartService $cartService;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();
        $shippingAddressId = Uuid::randomHex();

        $options = [
            'defaultShippingAddressId' => $shippingAddressId,
        ];

        $additionalAddress = [
            'id' => $shippingAddressId,
            'countryId' => $this->getValidCountryId(),
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Maximilian',
            'lastName' => 'Musterfrau',
            'street' => 'Ebbinghoff 10a',
            'zipcode' => '48624',
            'city' => 'Schöppingen',
        ];

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer($options, $additionalAddress),
            ]
        );
        $this->salesChannelContext->setRuleIds([$priceRuleId]);

        $this->renderer = static::getContainer()->get(ZugferdRenderer::class);
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->productRepository = static::getContainer()->get('product.repository');
    }

    public function testDocumentSnapshot(): void
    {
        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);

        $config = [
            'companyName' => 'Example Company',
            'documentDate' => '2023-11-24T12:00:00+00:00',
        ];

        $operation = new DocumentGenerateOperation(
            $orderId,
            ZugferdRenderer::FILE_EXTENSION,
            $config
        );

        $processedTemplate = $this->renderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig(),
        );

        $renderedDocument = $processedTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $renderedDocument);

        $content = $renderedDocument->getContent();
        static::assertIsString($content);

        $this->assertSnapshot('zugferd_document_default', [
            [
                'type' => self::TYPE_XML,
                'actual' => $content,
            ],
        ]);
    }

    /**
     * @param array<int|string, int> $taxes
     */
    private function generateDemoCart(array $taxes): Cart
    {
        $cart = $this->cartService->createNew('A');

        $products = [];

        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        foreach ($taxes as $index => $tax) {
            $price = 100.0 + (int) $index;
            $name = 'product ' . $index;
            $number = 'p' . $index;

            $product = (new ProductBuilder($ids, $number))
                ->price($price)
                ->name($name)
                ->active(true)
                ->tax('test-' . Uuid::randomHex(), $tax)
                ->visibility()
                ->build();

            $products[] = $product;

            $lineItems[] = $factory->create(['id' => $ids->get($number), 'referencedId' => $ids->get($number)], $this->salesChannelContext);
            $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);
        }

        $this->productRepository->create($products, Context::createDefaultContext());

        return $this->cartService->add($cart, $lineItems, $this->salesChannelContext);
    }
}
