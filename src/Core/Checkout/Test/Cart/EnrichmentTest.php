<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EnrichmentTest extends KernelTestCase
{
    /**
     * @var EntityRepository
     */
    protected $repository;

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

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();

        $this->repository = self::$container->get('product.repository');
        $this->factory = self::$container->get(CheckoutContextFactory::class);
        $this->context = $this->factory->create(Defaults::TENANT_ID, Defaults::TENANT_ID, Defaults::TOUCHPOINT);
        $this->enrichment = self::$container->get(Enrichment::class);

        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testMissingProductData()
    {
        $id = Uuid::uuid4()->getHex();

        $this->repository->create([
            [
                'id' => $id,
                'name' => 'Missing label',
                'description' => 'Missing description',
                'price' => ['gross' => 15, 'net' => 15],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 15],
                'media' => [
                    [
                        'id' => $id,
                        'name' => 'test',
                        'isCover' => true,
                        'media' => [
                            'name' => 'test',
                            'album' => ['name' => 'test'],
                            'mimeType' => 'A',
                            'fileName' => 'test',
                            'fileSize' => 0,
                        ],
                    ],
                ],
            ],
        ], $this->context->getContext());

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $id])
        );

        $enriched = $this->enrichment->enrich($cart, $this->context);

        self::assertCount(1, $enriched->getLineItems());
        self::assertTrue($enriched->getLineItems()->has('A'));

        $product = $enriched->getLineItems()->get('A');
        self::assertSame('Missing label', $product->getLabel());
        self::assertSame('Missing description', $product->getDescription());

        /** @var QuantityPriceDefinition $price */
        $price = $product->getPriceDefinition();
        self::assertInstanceOf(QuantityPriceDefinition::class, $price);

        self::assertSame(15.0, $price->getPrice());
        self::assertCount(1, $price->getTaxRules());
        self::assertTrue($price->getTaxRules()->has(15));

        self::assertInstanceOf(MediaStruct::class, $product->getCover());
        self::assertSame('test', $product->getCover()->getName());
    }

    public function testProductCollectorDoNotOverrideData()
    {
        $id = Uuid::uuid4()->getHex();

        $this->repository->create([
            [
                'id' => $id,
                'name' => 'Missing label',
                'description' => 'Missing description',
                'price' => ['gross' => 15, 'net' => 15],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 15],
                'media' => [
                    [
                        'id' => $id,
                        'name' => 'test',
                        'isCover' => true,
                        'media' => [
                            'name' => 'test',
                            'album' => ['name' => 'test'],
                            'mimeType' => 'A',
                            'fileName' => 'test',
                            'fileSize' => 0,
                        ],
                    ],
                ],
            ],
        ], $this->context->getContext());

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('A', 'product'))
                ->setPayload(['id' => $id])
                ->setPriceDefinition(new QuantityPriceDefinition(1, new TaxRuleCollection()))
                ->setDescription('Do not override')
                ->setCover(
                    (new MediaStruct())->assign([
                        'name' => 'Do not override',
                        'fileName' => 'Do not override',
                        'fileSize' => 10,
                        'mimeType' => 'B'
                    ])
                )
        );

        $enriched = $this->enrichment->enrich($cart, $this->context);

        self::assertCount(1, $enriched->getLineItems());
        self::assertTrue($enriched->getLineItems()->has('A'));

        $product = $enriched->getLineItems()->get('A');
        self::assertSame('Missing label', $product->getLabel());
        self::assertSame('Do not override', $product->getDescription());

        /** @var QuantityPriceDefinition $price */
        $price = $product->getPriceDefinition();
        self::assertInstanceOf(QuantityPriceDefinition::class, $price);

        self::assertSame(1.0, $price->getPrice());
        self::assertCount(0, $price->getTaxRules());

        self::assertInstanceOf(MediaStruct::class, $product->getCover());
        self::assertSame('Do not override', $product->getCover()->getName());
    }
}
