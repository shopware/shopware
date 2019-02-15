<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

use Doctrine\DBAL\Connection;

abstract class ValueGeneratorConnector implements ValueGeneratorConnectorInterface
{
    /**
     * @var Connection
     */
    protected $connection;
    /**
     * @var ValueGeneratorInterface
     */
    protected $valueGenerator;

    protected $connectorId = '';

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function setGenerator(ValueGeneratorInterface $valueGenerator)
    {
        $this->valueGenerator = $valueGenerator;
    }

    abstract public function pullState();

    /**
     * @return string
     */
    public function getConnectorId(): string
    {
        return $this->connectorId;
    }
}
