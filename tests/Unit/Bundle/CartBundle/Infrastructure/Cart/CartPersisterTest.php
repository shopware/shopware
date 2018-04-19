<?php
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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Infrastructure\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Cart\LineItem\LineItemCollection;
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
            $persister->load('not_existing_token');
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

        $cart = $persister->load('existing');

        $this->assertEquals(
            new CartContainer('shopware', 'existing', new LineItemCollection(), new ErrorCollection()),
            $cart
        );
    }

    public function testSave(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeUpdate');

        $persister = new CartPersister(
            $connection,
            new JsonSerializer(new ObjectDeserializer())
        );

        $persister->save(new CartContainer('shopware', 'existing', new LineItemCollection(), new ErrorCollection()));
    }
}
