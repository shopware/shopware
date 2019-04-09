<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;

class SalesChannelContextPersister
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function save(string $token, array $parameters): void
    {
        $existing = $this->load($token);

        $parameters = array_replace_recursive($existing, $parameters);

        $this->connection->executeUpdate(
            'REPLACE INTO storefront_api_context (`token`, `payload`) VALUES (:token, :payload)',
            [
                'token' => $token,
                'payload' => json_encode($parameters),
            ]
        );
    }

    public function load(string $token): array
    {
        $parameter = $this->connection->fetchColumn(
            'SELECT `payload` FROM storefront_api_context WHERE token = :token',
            ['token' => $token]
        );

        if (!$parameter) {
            return [];
        }

        return array_filter(json_decode($parameter, true));
    }
}
