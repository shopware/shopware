<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Struct\Uuid;

class CheckoutContextPersister
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function save(string $token, array $parameters, string $tenantId): void
    {
        $existing = $this->load($token, $tenantId);

        $parameters = array_replace_recursive($existing, $parameters);

        $this->connection->executeUpdate(
            'REPLACE INTO storefront_api_context (`token`, `tenant_id`, `payload`) VALUES (:token, :tenant, :payload)',
            [
                'token' => Uuid::fromHexToBytes($token),
                'tenant' => Uuid::fromHexToBytes($tenantId),
                'payload' => json_encode($parameters),
            ]
        );
    }

    public function load(string $token, string $tenantId): array
    {
        if (!Uuid::isValid($token)) {
            return [];
        }

        $parameter = $this->connection->fetchColumn(
            'SELECT `payload` FROM storefront_api_context WHERE token = :token AND tenant_id = :tenant',
            ['token' => Uuid::fromHexToBytes($token), 'tenant' => Uuid::fromHexToBytes($tenantId)]
        );

        if (!$parameter) {
            return [];
        }

        return array_filter(json_decode($parameter, true));
    }
}
