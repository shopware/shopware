<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

use Doctrine\DBAL\Connection;

//use Redis;

class ValueGeneratorRedisConnector implements ValueGeneratorConnectorInterface
{
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var ValueGeneratorInterface
     */
    private $valueGenerator;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function setGenerator(ValueGeneratorInterface $valueGenerator)
    {
        $this->valueGenerator = $valueGenerator;
    }

    public function pullState()
    {
        /*
        $increment = $this->valueGenerator->incrementBy();
        $redis = new Redis();
        $redis->connect('172.17.0.1');
        $redVal = $redis->incrBy(
            'numberRangeState:52352352352300000000000000000000', $increment
        );
        if ($redVal === $increment) {
            $stmt = $this->connection->executeQuery(
                "SELECT `last_value` FROM `number_range_state` WHERE HEX(`number_range_id`) = '52352352352300000000000000000000'"
            );
            $lastNumber = $stmt->fetchColumn();
            $redis->set('numberRangeState:52352352352300000000000000000000', $lastNumber + $increment);
        } else {
            $lastNumber = $redVal-$increment;
        }

        return $lastNumber;*/
        return '';
    }
}
