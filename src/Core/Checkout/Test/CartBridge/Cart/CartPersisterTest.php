<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\CartBridge\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Symfony\Component\Serializer\Encoder\ChainDecoder;
use Symfony\Component\Serializer\Encoder\ChainEncoder;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class CartPersisterTest extends TestCase
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->serializer = new Serializer(
            [new StructNormalizer(), new JsonSerializableNormalizer(), new DateTimeNormalizer(), new DateIntervalNormalizer(), new ArrayDenormalizer(), new ObjectNormalizer(), new PropertyNormalizer()],
            [
                new ChainDecoder([
                    new JsonDecode(true),
                ]),
                new ChainEncoder([
                    new JsonEncode(), new YamlEncoder(), new CsvEncoder(), new XmlEncoder(),
                ]), ]
        );
    }

    public function testLoadWithNotExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchColumn')
            ->will(static::returnValue(false));

        $persister = new CartPersister($connection, $this->serializer);

        $e = null;
        try {
            $persister->load('not_existing_token', 'shopware', Generator::createContext());
        } catch (\Exception $e) {
        }

        /* @var CartTokenNotFoundException $e */
        static::assertInstanceOf(CartTokenNotFoundException::class, $e);
        static::assertSame('not_existing_token', $e->getToken());
    }

    public function testLoadWithExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchColumn')
            ->will(
                static::returnValue(
                json_encode([
                    '_class' => Cart::class,
                    'lineItems' => new LineItemCollection(),
                    'token' => 'existing',
                    'name' => 'shopware',
                    'errors' => new ErrorCollection(),
                ])
            ));

        $persister = new CartPersister($connection, $this->serializer);
        $cart = $persister->load('existing', 'shopware', Generator::createContext());

        static::assertEquals(
            new Cart('shopware', 'existing'),
            $cart
        );
    }

    public function testEmptyCartShouldnBeSaved(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())->method('insert');

        $persister = new CartPersister($connection, $this->serializer);

        $calc = new Cart('shopware', 'existing');

        $persister->save($calc, Generator::createContext());
    }

    public function testSaveWithItems(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('executeUpdate');

        $persister = new CartPersister($connection, $this->serializer);

        $calc = new Cart('shopware', 'existing');
        $calc->add(
            (new LineItem('A', 'test'))
                ->setPrice(new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister->save($calc, Generator::createContext());
    }
}
