<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\UuidGenerator;

use Doctrine\DBAL\Connection;

abstract class NumberGenerator implements Generator
{
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $prefix;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection, string $name, string $prefix)
    {
        $this->connection = $connection;
        $this->name = $name;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function create(): string
    {
        $this->connection->beginTransaction();
        try {
            $number = $this->connection->fetchColumn('SELECT number FROM s_order_number WHERE name = ? FOR UPDATE', [$this->name]);

            if ($number === false) {
                throw new \RuntimeException(sprintf('Number range with name "%s" does not exist.', $this->name));
            }

            $number += 1000;

            $this->connection->executeUpdate('UPDATE s_order_number SET number = number + 1 WHERE name = ?', [$this->name]);
            $number += 1;
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $this->prefix . $number;
    }
}