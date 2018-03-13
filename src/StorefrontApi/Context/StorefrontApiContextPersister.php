<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Context;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class StorefrontApiContextPersister
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function save(string $token, array $parameter): void
    {
        $existing = $this->load($token);

        $parameter = array_replace_recursive($existing, $parameter);

        $this->connection->executeUpdate(
            'REPLACE INTO storefront_api_context (`token`, `payload`) VALUES (:token, :payload)',
            [
                'token' => Uuid::fromString($token)->getBytes(),
                'payload' => json_encode($parameter),
            ]
        );
    }

    public function load(string $token): array
    {
        if (!Uuid::isValid($token)) {
            return [];
        }

        $parameter = $this->connection->fetchColumn(
            'SELECT `payload` FROM storefront_api_context WHERE token = :token',
            ['token' => Uuid::fromString($token)->getBytes()]
        );

        if (!$parameter) {
            return [];
        }

        return json_decode($parameter, true);
    }
}
