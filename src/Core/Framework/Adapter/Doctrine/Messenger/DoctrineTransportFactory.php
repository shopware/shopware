<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Doctrine\Messenger;

use Doctrine\DBAL\Connection as DBALConnection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

#[Package('core')]
class DoctrineTransportFactory implements TransportFactoryInterface
{
    public function __construct(private readonly DBALConnection $connection)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        unset($options['transport_name'], $options['use_notify']);

        // Always allow PostgreSQL-specific keys, to be able to transparently fallback to the native driver when LISTEN/NOTIFY isn't available
        $configuration = Connection::buildConfiguration($dsn, $options);

        $connection = new Connection($configuration, $this->connection);

        return new DoctrineTransport($connection, $serializer);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'doctrine://');
    }
}
