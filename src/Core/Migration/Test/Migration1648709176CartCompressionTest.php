<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1648709176CartCompression;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 * NEXT-21735 - Not deterministic due to SalesChannelContextFactory
 *
 * @group not-deterministic
 */
#[Package('core')]
class Migration1648709176CartCompressionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrateCarts(): void
    {
        $this->restoreOldColumn();

        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create($token, TestDefaults::SALES_CHANNEL);

        $this->getContainer()->get(CartPersister::class)->save($cart, $context);

        $migration = new Migration1648709176CartCompression();
        $migration->updateDestructive($this->connection);

        $loaded = $this->getContainer()->get(CartPersister::class)->load($token, $context);

        static::assertInstanceOf(Cart::class, $loaded);
        static::assertCount(1, $loaded->getLineItems());
        static::assertEquals('test', $loaded->getLineItems()->first()->getId());
    }

    /**
     * @dataProvider compressionProvider
     */
    public function testCompression(CartPersister $saver, CartPersister $loader): void
    {
        $origin = new Cart('existing');

        $origin->addLineItems(new LineItemCollection([
            new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1),
            new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1),
        ]));

        $context = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create($origin->getToken(), TestDefaults::SALES_CHANNEL);

        $saver->save($origin, $context);

        $cart = $loader->load($origin->getToken(), $context);

        static::assertSame($cart->getToken(), $origin->getToken());
        static::assertCount(2, $cart->getLineItems());
    }

    /**
     * @dataProvider compressionProvider
     */
    public function testCompressionWithNewPayloadField(CartPersister $saver, CartPersister $loader): void
    {
        $this->restoreOldColumn();

        try {
            (new Migration1648709176CartCompression())
                ->updateDestructive($this->getContainer()->get(Connection::class)); // test duplicate execution

            (new Migration1648709176CartCompression())
                ->updateDestructive($this->getContainer()->get(Connection::class));

            $this->testCompression($saver, $loader);
        } catch (\Exception $e) {
            static::fail($e->getMessage());
        }
    }

    public function compressionProvider(): \Generator
    {
        $compressed = new CartPersister(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(CartSerializationCleaner::class),
            true
        );

        $uncompressed = new CartPersister(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(CartSerializationCleaner::class),
            true
        );

        yield 'Load and save uncompressed' => [$compressed, $compressed];
        yield 'Load and save compressed' => [$uncompressed, $uncompressed];
        yield 'Save compressed and load uncompressed' => [$compressed, $uncompressed];
        yield 'Save uncompressed and load compressed' => [$uncompressed, $compressed];
    }

    private function restoreOldColumn(): void
    {
        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM `cart`');

        if (EntityDefinitionQueryHelper::columnExists($this->getContainer()->get(Connection::class), 'cart', 'payload')) {
            $this->getContainer()->get(Connection::class)->executeStatement('ALTER TABLE `cart` DROP `payload`');
        }

        if (EntityDefinitionQueryHelper::columnExists($this->getContainer()->get(Connection::class), 'cart', 'compressed')) {
            $this->getContainer()->get(Connection::class)->executeStatement('ALTER TABLE `cart` DROP `compressed`');
        }

        if (!EntityDefinitionQueryHelper::columnExists($this->getContainer()->get(Connection::class), 'cart', 'cart')) {
            $this->getContainer()->get(Connection::class)->executeStatement('ALTER TABLE `cart` ADD COLUMN `cart` longtext NOT NULL');
        }
    }
}
