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
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\Price\Struct\CartPrice;
use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Cart\Test\Common\Generator;
use Shopware\CartBridge\Cart\CartPersister;
use Shopware\Serializer\JsonSerializer;
use Shopware\Serializer\ObjectDeserializer;

class CartPersisterTest extends TestCase
{
    public function testLoadWithNotExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchColumn')
            ->will($this->returnValue(false));

        $persister = new CartPersister(
            $connection,
            new JsonSerializer(new ObjectDeserializer())
        );

        $e = null;
        try {
            $persister->load('not_existing_token', 'shopware');
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
                    '_class' => CartContainer::class,
                    'lineItems' => new LineItemCollection(),
                    'token' => 'existing',
                    'name' => 'shopware',
                    'errors' => new ErrorCollection(),
                ])
            ));

        $persister = new CartPersister(
            $connection,
            new JsonSerializer(new ObjectDeserializer())
        );

        $cart = $persister->load('existing', 'shopware');

        $this->assertEquals(
            new CartContainer('shopware', 'existing', new LineItemCollection(), new ErrorCollection()),
            $cart
        );
    }

    public function testEmptyCartShouldnBeSaved(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('insert');

        $persister = new CartPersister(
            $connection,
            new JsonSerializer(new ObjectDeserializer())
        );

        $calc = new CalculatedCart(
            new CartContainer('shopware', 'existing', new LineItemCollection(), new ErrorCollection()),
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

        $persister = new CartPersister(
            $connection,
            new JsonSerializer(new ObjectDeserializer())
        );

        $calc = new CalculatedCart(
            new CartContainer('shopware', 'existing', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([
                new CalculatedLineItem(
                    'A',
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    1,
                    'test',
                    null,
                    null
                ),
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );
        $persister->save($calc, Generator::createContext());
    }
}
