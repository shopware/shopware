<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Command\CartMigrateCommand;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @internal
 */
#[Package('checkout')]
class CartMigrateCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testWithRedisPrefix(): void
    {
        $url = EnvironmentHelper::getVariable('REDIS_URL');

        if (!$url) {
            static::markTestSkipped('No redis server configured');
        }

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM cart');

        $redisCart = new Cart(Uuid::randomHex());
        $redisCart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->getSalesChannelContext($redisCart->getToken());

        $factory = new RedisConnectionFactory('test-prefix-');
        $redis = $factory->create((string) $url);
        $redis->flushAll();

        $persister = new RedisCartPersister($redis, $this->getContainer()->get('event_dispatcher'), $this->getContainer()->get(CartSerializationCleaner::class), false, 90);
        $persister->save($redisCart, $context);

        $command = new CartMigrateCommand($redis, $this->getContainer()->get(Connection::class), false, 90, $factory);
        $command->run(new ArrayInput(['from' => 'redis']), new NullOutput());

        $persister = new CartPersister(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(CartSerializationCleaner::class),
            false
        );

        $sqlCart = $persister->load($redisCart->getToken(), $context);

        static::assertInstanceOf(Cart::class, $sqlCart);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testRedisToSql(bool $sqlCompressed, bool $redisCompressed): void
    {
        $url = EnvironmentHelper::getVariable('REDIS_URL');

        if (!$url) {
            static::markTestSkipped('No redis server configured');
        }

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM cart');

        $redisCart = new Cart(Uuid::randomHex());
        $redisCart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->getSalesChannelContext($redisCart->getToken());

        $factory = $this->getContainer()->get(RedisConnectionFactory::class);
        $redis = $factory->create((string) $url);
        $redis->flushAll();

        $persister = new RedisCartPersister($redis, $this->getContainer()->get('event_dispatcher'), $this->getContainer()->get(CartSerializationCleaner::class), $redisCompressed, 90);
        $persister->save($redisCart, $context);

        $command = new CartMigrateCommand($redis, $this->getContainer()->get(Connection::class), $sqlCompressed, 90, $factory);
        $command->run(new ArrayInput(['from' => 'redis']), new NullOutput());

        $persister = new CartPersister(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(CartSerializationCleaner::class),
            $sqlCompressed
        );

        $sqlCart = $persister->load($redisCart->getToken(), $context);

        static::assertInstanceOf(Cart::class, $sqlCart);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSqlToRedis(bool $sqlCompressed, bool $redisCompressed): void
    {
        $url = EnvironmentHelper::getVariable('REDIS_URL');

        if (!$url) {
            static::markTestSkipped('No redis server configured');
        }

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM cart');

        $sqlCart = new Cart(Uuid::randomHex());
        $sqlCart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->getSalesChannelContext($sqlCart->getToken());

        $persister = new CartPersister(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(CartSerializationCleaner::class),
            $sqlCompressed
        );

        $persister->save($sqlCart, $context);

        $token = $this->getContainer()->get(Connection::class)->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $sqlCart->getToken()]);
        static::assertNotEmpty($token);

        $factory = $this->getContainer()->get(RedisConnectionFactory::class);
        $redis = $factory->create((string) $url);
        $redis->flushAll();

        $command = new CartMigrateCommand($redis, $this->getContainer()->get(Connection::class), $sqlCompressed, 90, $factory);
        $command->run(new ArrayInput(['from' => 'sql']), new NullOutput());

        $persister = new RedisCartPersister($redis, $this->getContainer()->get('event_dispatcher'), $this->getContainer()->get(CartSerializationCleaner::class), $redisCompressed, 90);
        $redisCart = $persister->load($sqlCart->getToken(), $context);

        static::assertInstanceOf(Cart::class, $redisCart);
    }

    public static function dataProvider(): \Generator
    {
        yield 'Test sql compressed and redis compressed' => [true, true];
        yield 'Test sql uncompressed and redis uncompressed' => [false, false];
        yield 'Test sql uncompressed and redis compressed' => [false, true];
        yield 'Test sql compressed and redis uncompressed' => [true, false];
    }

    private function getSalesChannelContext(string $token): SalesChannelContext
    {
        return $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create($token, TestDefaults::SALES_CHANNEL);
    }
}
