<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

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
     * @var CheckoutContextFactory
     */
    private $factory;

    /**
     * @var CheckoutContext
     */
    private $context;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->factory = $this->getContainer()->get(CheckoutContextFactory::class);
        $this->context = $this->factory->create(Uuid::uuid4()->getHex(), Defaults::SALES_CHANNEL);
        $this->enrichment = $this->getContainer()->get(Enrichment::class);
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMissingProductData(): void
    {
        $id = Uuid::uuid4()->getHex();

        $context = $this->context->getContext();
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->productRepository->create([
            [
                'id' => $id,
                'name' => 'Missing label',
                'description' => 'Missing description',
                'price' => ['gross' => 15, 'net' => 15],
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
        ], $context);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $id])
        );

        $enriched = $this->enrichment->enrich($cart, $this->context);

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
        $id = Uuid::uuid4()->getHex();

        $context = $this->context->getContext();
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->productRepository->create([
            [
                'id' => $id,
                'name' => 'Missing label',
                'description' => 'Missing description',
                'price' => ['gross' => 15, 'net' => 15],
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
        ], $context);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $id])
                ->setPriceDefinition(new QuantityPriceDefinition(1, new TaxRuleCollection()))
                ->setDescription('Do not override')
                ->setCover(
                    (new MediaEntity())->assign([
                        'fileName' => 'Do not override',
                        'fileSize' => 10,
                        'mimeType' => 'B',
                    ])
                )
        );

        $enriched = $this->enrichment->enrich($cart, $this->context);

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
