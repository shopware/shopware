<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class EnrichmentTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var EntityRepository
     */
    protected $productRepository;

    /**
     * @var Enrichment
     */
    protected $enrichment;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var SalesChannelContextFactory
     */
    private $factory;

    /**
     * @var SalesChannelContext
     */
    private $context;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->factory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->context = $this->factory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->enrichment = $this->getContainer()->get(Enrichment::class);
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMissingProductData(): void
    {
        $id = Uuid::randomHex();
        $productNumber = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $id,
                'productNumber' => $productNumber,
                'name' => 'Missing label',
                'stock' => 1,
                'description' => 'Missing description',
                'price' => ['gross' => 15, 'net' => 15, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'cover' => [
                    'id' => $id,
                    'name' => 'test',
                    'media' => [
                        'mimeType' => 'image/jpeg',
                        'fileExtension' => 'jpeg',
                        'fileName' => 'test',
                        'fileSize' => 0,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $id])
        );

        $enriched = $this->enrichment->enrich($cart, $this->context, new CartBehavior());

        static::assertCount(1, $enriched->getLineItems());
        static::assertTrue($enriched->getLineItems()->has('A'));

        $product = $enriched->getLineItems()->get('A');
        static::assertSame('Missing label', $product->getLabel());
        static::assertSame('Missing description', $product->getDescription());

        /** @var QuantityPriceDefinition $price */
        $price = $product->getPriceDefinition();
        static::assertInstanceOf(QuantityPriceDefinition::class, $price);

        static::assertSame(15.0, $price->getPrice());
        static::assertCount(1, $price->getTaxRules());
        static::assertTrue($price->getTaxRules()->has(15));

        static::assertInstanceOf(MediaEntity::class, $product->getCover());
        static::assertSame('test', $product->getCover()->getFileName());
    }

    public function testProductCollectorDoNotOverrideData(): void
    {
        $id = Uuid::randomHex();
        $productNumber = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $id,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Missing label',
                'description' => 'Missing description',
                'price' => ['gross' => 15, 'net' => 15, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'media' => [
                    [
                        'id' => $id,
                        'name' => 'test',
                        'media' => [
                            'name' => 'test',
                            'mimeType' => 'image/jpeg',
                            'fileExtension' => 'jpeg',
                            'fileName' => 'test',
                            'fileSize' => 0,
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $id])
                ->setPriceDefinition(new QuantityPriceDefinition(1, new TaxRuleCollection(), 2))
                ->setDescription('Do not override')
                ->setCover(
                    (new MediaEntity())->assign([
                        'fileName' => 'Do not override',
                        'fileSize' => 10,
                        'mimeType' => 'B',
                    ])
                )
        );

        $enriched = $this->enrichment->enrich($cart, $this->context, new CartBehavior());

        static::assertCount(1, $enriched->getLineItems());
        static::assertTrue($enriched->getLineItems()->has('A'));

        $product = $enriched->getLineItems()->get('A');
        static::assertSame('Missing label', $product->getLabel());
        static::assertSame('Do not override', $product->getDescription());

        /** @var QuantityPriceDefinition $price */
        $price = $product->getPriceDefinition();
        static::assertInstanceOf(QuantityPriceDefinition::class, $price);

        static::assertSame(1.0, $price->getPrice());
        static::assertCount(0, $price->getTaxRules());

        static::assertInstanceOf(MediaEntity::class, $product->getCover());
        static::assertSame('Do not override', $product->getCover()->getFileName());
    }
}
