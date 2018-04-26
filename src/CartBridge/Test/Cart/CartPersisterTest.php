<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\CartBridge\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Price\Struct\CartPrice;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Cart\Test\Common\Generator;
use Shopware\CartBridge\Cart\CartPersister;
use Shopware\Framework\Serializer\StructNormalizer;
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
        $connection->expects($this->once())
            ->method('fetchColumn')
            ->will($this->returnValue(false));

        $persister = new CartPersister($connection, $this->serializer);

        $e = null;
        try {
            $persister->load('not_existing_token', 'shopware', Generator::createContext());
        } catch (\Exception $e) {
        }

        /* @var CartTokenNotFoundException $e */
        $this->assertInstanceOf(CartTokenNotFoundException::class, $e);
        $this->assertSame('not_existing_token', $e->getToken());
    }

    public function testLoadWithExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchColumn')
            ->will($this->returnValue(
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

        $this->assertEquals(
            new Cart('shopware', 'existing', new LineItemCollection(), new ErrorCollection()),
            $cart
        );
    }

    public function testEmptyCartShouldnBeSaved(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('insert');

        $persister = new CartPersister($connection, $this->serializer);

        $calc = new CalculatedCart(
            new Cart('shopware', 'existing', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        $persister->save($calc, Generator::createContext());
    }

    public function testSaveWithItems(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeUpdate');

        $persister = new CartPersister($connection, $this->serializer);

        $calc = new CalculatedCart(
            new Cart('shopware', 'existing', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([
                new CalculatedLineItem(
                    'A',
                    new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    1,
                    'test',
                    'label'
                ),
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        $persister->save($calc, Generator::createContext());
    }
}
