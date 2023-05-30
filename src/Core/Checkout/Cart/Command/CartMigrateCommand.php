<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Traits\RedisClusterProxy;
use Symfony\Component\Cache\Traits\RedisProxy;
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
     * @param \Redis|\RedisArray|\RedisCluster|RedisClusterProxy|RedisProxy|null $redis
     *
     * @phpstan-ignore-next-line ignore can be removed in 6.6.0 when all props are natively typed
     */
    public function __construct(
        /** @deprecated tag:v6.6.0 - Property will be natively typed and become private and readonly */
        protected $redis,
        /** @deprecated tag:v6.6.0 - Property will become private and readonly */
        protected Connection $connection,
        private readonly bool $compress,
        private readonly int $expireDays,
        private readonly RedisConnectionFactory $factory
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

        if (empty($keys)) {
            $this->io->success('No carts found in Redis');

            return 0;
        }
        $this->progress = $this->io->createProgressBar(\count($keys));
        $this->progress->setFormat("<info>[%message%]</info>\n%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%");
        $this->progress->setMessage('Migrating carts from Redis to SQL');

        $queue = new MultiInsertQueryQueue($this->connection, 50, false, true);

        // @deprecated tag:v6.6.0 - payload always exists
        $payloadExists = EntityDefinitionQueryHelper::columnExists($this->connection, 'cart', 'payload');

        foreach ($keys as $index => $key) {
            $key = \substr((string) $key, \strlen($this->redis->_prefix('')));

            $value = $this->redis->get($key);
            if (!\is_string($value)) {
                continue;
            }

            $value = \unserialize($value);

            if (!\array_key_exists('sales_channel_id', $value)) {
                $this->io->writeln('<error>Sales channel id is missing for key ' . $key . '. Carts created before 6.4.12 can not be migrated</error>');

                continue;
            }

            $content = $value['compressed'] ? CacheValueCompressor::uncompress($value['content']) : \unserialize($value['content']);

            unset($value['content'], $value['compressed']);

            // @deprecated tag:v6.6.0 - payload always exists - keep IF body
            if ($payloadExists) {
                $value['payload'] = $this->compress ? CacheValueCompressor::compress($content['cart']) : serialize($content['cart']);
                $value['compressed'] = $this->compress ? 1 : 0;
            } else {
                $value['cart'] = serialize($content['cart']);
            }

            $value['rule_ids'] = \json_encode($value['rule_ids'], \JSON_THROW_ON_ERROR);
            $value['customer_id'] = $value['customer_id'] ? Uuid::fromHexToBytes($value['customer_id']) : null;
            $value['currency_id'] = Uuid::fromHexToBytes($value['currency_id']);
            $value['shipping_method_id'] = Uuid::fromHexToBytes($value['shipping_method_id']);
            $value['payment_method_id'] = Uuid::fromHexToBytes($value['payment_method_id']);
            $value['country_id'] = Uuid::fromHexToBytes($value['country_id']);
            $value['sales_channel_id'] = Uuid::fromHexToBytes($value['sales_channel_id']);

            $queue->addInsert('cart', $value);

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

        // @deprecated tag:v6.6.0 - payload always exists
        $payloadExists = EntityDefinitionQueryHelper::columnExists($this->connection, 'cart', 'payload');

        while ($tokens = $iterator->fetch()) {
            $rows = $this->connection->fetchAllAssociative('SELECT * FROM cart WHERE token IN (:tokens)', ['tokens' => $tokens], ['tokens' => ArrayParameterType::STRING]);

            $values = [];
            foreach ($rows as $row) {
                $key = RedisCartPersister::PREFIX . $row['token'];

                // @deprecated tag:v6.6.0 - keep if body, remove complete else
                if ($payloadExists) {
                    $cart = $row['compressed'] ? CacheValueCompressor::uncompress($row['payload']) : unserialize((string) $row['payload']);
                } else {
                    $cart = \unserialize($row['cart']);
                }

                $content = ['cart' => $cart, 'rule_ids' => \json_decode((string) $row['rule_ids'], true, 512, \JSON_THROW_ON_ERROR)];

                $content = $this->compress ? CacheValueCompressor::compress($content) : \serialize($content);

                $values[$key] = $row['token'];
                $value = \serialize([
                    'compressed' => $this->compress,
                    'content' => $content,
                    // used for migration
                    'token' => $row['token'],
                    'customer_id' => $row['customer_id'] ? Uuid::fromBytesToHex($row['customer_id']) : null,
                    'rule_ids' => \json_decode((string) $row['rule_ids'], true, 512, \JSON_THROW_ON_ERROR),
                    'currency_id' => Uuid::fromBytesToHex($row['currency_id']),
                    'shipping_method_id' => Uuid::fromBytesToHex($row['shipping_method_id']),
                    'payment_method_id' => Uuid::fromBytesToHex($row['payment_method_id']),
                    'country_id' => Uuid::fromBytesToHex($row['country_id']),
                    'sales_channel_id' => Uuid::fromBytesToHex($row['sales_channel_id']),
                    'price' => $row['price'],
                    'line_item_count' => $row['line_item_count'],
                    'created_at' => $row['created_at'],
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
