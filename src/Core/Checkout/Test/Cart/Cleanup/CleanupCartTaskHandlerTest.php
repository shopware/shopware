<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cleanup\CleanupCartTaskHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class CleanupCartTaskHandlerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private CleanupCartTaskHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = $this->getContainer()->get(CleanupCartTaskHandler::class);
    }

    public function testCleanup(): void
    {
        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM cart');

        $ids = new IdsCollection();

        $date = new \DateTime();

        $this->createCart($ids->create('cart-1'), $date);

        $date->modify(sprintf('-%s day', 121));
        $this->createCart($ids->create('cart-2'), $date);

        $this->handler->run();

        $carts = $this->getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT token FROM cart');

        static::assertCount(1, $carts);
        static::assertContains($ids->get('cart-1'), $carts);
    }

    private function createCart(string $token, \DateTime $date): void
    {
        $cart = [
            'token' => $token,
            'name' => 'test',
            'cart' => '',
            'price' => 1,
            'line_item_count' => 1,
            'rule_ids' => json_encode([]),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'shipping_method_id' => $this->getContainer()->get(Connection::class)->fetchOne('SELECT id FROM shipping_method LIMIT 1'),
            'payment_method_id' => $this->getContainer()->get(Connection::class)->fetchOne('SELECT id FROM payment_method LIMIT 1'),
            'country_id' => $this->getContainer()->get(Connection::class)->fetchOne('SELECT id FROM country LIMIT 1'),
            'customer_id' => null,
            'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
            'created_at' => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->getContainer()->get(Connection::class)
            ->insert('cart', $cart);
    }
}
