<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cart:migrate',
    description: 'Migrate carts from redis to database',
)]
#[Package('checkout')]
class CartMigrateCommand extends Command
{
    use ConsoleProgressTrait;

    /**
     * @internal
     *
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Relay\Relay|null $redis
     *
     * @phpstan-ignore-next-line param cannot be natively typed, as symfony might change the type in the future
     */
    public function __construct(
        private $redis,
        private readonly Connection $connection,
        private readonly int $expireDays,
        private readonly RedisConnectionFactory $factory,
        private readonly CartCompressor $cartCompressor
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addArgument('from', InputArgument::REQUIRED, 'Defines the source storage (redis or sql)')
            ->addArgument('url', InputArgument::OPTIONAL, 'Allows to define a redis connection url')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');

        if ($url !== null) {
            $this->redis = $this->factory->create($url);
        }

        if ($this->redis === null) {
            throw new \RuntimeException('%shopware.cart.redis_url% is not configured and no url provided.');
        }

        $from = $input->getArgument('from');
        if (!\in_array($from, ['redis', 'sql'], true)) {
            throw new \RuntimeException('Invalid source storage: ' . $from . '. Valid values are "redis" or "sql".');
        }

        if ($from === 'redis') {
            return $this->redisToSql($input, $output);
        }

        return $this->sqlToRedis($input, $output);
    }

    protected function createIterator(): LastIdQuery
    {
        $query = $this->connection->createQueryBuilder();
        $query->addSelect(['cart.auto_increment', 'cart.token']);
        $query->from('cart');
        $query->andWhere('cart.auto_increment > :lastId');
        $query->addOrderBy('cart.auto_increment');
        $query->setMaxResults(50);
        $query->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }

    private function redisToSql(InputInterface $input, OutputInterface $output): int
    {
        if ($this->redis === null) {
            throw new \RuntimeException('%shopware.cart.redis_url% is not configured and no url provided.');
        }

        $this->io = new ShopwareStyle($input, $output);

        $keys = $this->redis->keys(RedisCartPersister::PREFIX . '*');
        \assert(\is_array($keys));

        if (empty($keys)) {
            $this->io->success('No carts found in Redis');

            return 0;
        }
        $this->progress = $this->io->createProgressBar(\count($keys));
        $this->progress->setFormat("<info>[%message%]</info>\n%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%");
        $this->progress->setMessage('Migrating carts from Redis to SQL');

        $queue = new MultiInsertQueryQueue($this->connection, 50, false, true);

        $created = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($keys as $index => $key) {
            if (\method_exists($this->redis, '_prefix')) {
                $key = \substr((string) $key, \strlen($this->redis->_prefix('')));
            }

            $value = $this->redis->get($key);
            if (!\is_string($value)) {
                continue;
            }

            $value = \unserialize($value);

            $content = $this->cartCompressor->unserialize($value['content'], (int) $value['compressed']);

            [$newCompression, $newCart] = $this->cartCompressor->serialize($content['cart']);

            $migratedCart = [];
            $migratedCart['token'] = substr($key, \strlen(RedisCartPersister::PREFIX));
            $migratedCart['payload'] = $newCart;
            $migratedCart['compressed'] = $newCompression;
            $migratedCart['rule_ids'] = \json_encode($content['rule_ids'], \JSON_THROW_ON_ERROR);
            $migratedCart['created_at'] = $created;

            $queue->addInsert('cart', $migratedCart);

            if ($index % 50 === 0) {
                $queue->execute();
            }

            $this->progress->advance();
        }

        $queue->execute();

        $this->progress->finish();

        $this->io->success('Migration from Redis to SQL was successful');

        $this->io->newLine(2);

        return self::SUCCESS;
    }

    private function sqlToRedis(InputInterface $input, OutputInterface $output): int
    {
        if ($this->redis === null) {
            throw new \RuntimeException('%shopware.cart.redis_url% is not configured and no url provided.');
        }

        $this->io = new ShopwareStyle($input, $output);

        $count = $this->connection->fetchOne('SELECT COUNT(token) FROM cart');

        if ($count === 0) {
            $this->io->success('No carts found in Redis');

            return 0;
        }

        $this->progress = $this->io->createProgressBar((int) $count);
        $this->progress->setFormat("<info>[%message%]</info>\n%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%");
        $this->progress->setMessage('Migrating carts from SQL to Redis');

        $iterator = $this->createIterator();

        while ($tokens = $iterator->fetch()) {
            $rows = $this->connection->fetchAllAssociative('SELECT * FROM cart WHERE token IN (:tokens)', ['tokens' => $tokens], ['tokens' => ArrayParameterType::STRING]);

            $values = [];
            foreach ($rows as $row) {
                $key = RedisCartPersister::PREFIX . $row['token'];

                $cart = $this->cartCompressor->unserialize($row['payload'], (int) $row['compressed']);

                $content = ['cart' => $cart, 'rule_ids' => \json_decode((string) $row['rule_ids'], true, 512, \JSON_THROW_ON_ERROR)];

                [$newCompression, $newCart] = $this->cartCompressor->serialize($content);

                $values[$key] = $row['token'];
                $value = \serialize([
                    'compressed' => $newCompression,
                    'content' => $newCart,
                ]);

                $this->redis->set($key, $value, ['EX' => $this->expireDays * 86400]);
            }

            $this->progress->advance(\count($values));
        }

        $this->progress->finish();

        $this->io->success('Migration from SQL to Redis was successful');

        $this->io->newLine(2);

        return self::SUCCESS;
    }
}
