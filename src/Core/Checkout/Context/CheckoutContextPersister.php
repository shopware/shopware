<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Exception\InvalidContextTokenException;
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

    public function save(string $token, array $parameters): void
    {
        $existing = $this->load($token);

        $parameters = array_replace_recursive($existing, $parameters);

        $this->connection->executeUpdate(
            'REPLACE INTO storefront_api_context (`token`, `payload`) VALUES (:token, :payload)',
            [
                'token' => Uuid::fromHexToBytes($token),
                'payload' => json_encode($parameters),
            ]
        );
    }

    public function load(string $token): array
    {
        if (!Uuid::isValid($token)) {
            throw new InvalidContextTokenException($token);
        }

        $parameter = $this->connection->fetchColumn(
            'SELECT `payload` FROM storefront_api_context WHERE token = :token',
            ['token' => Uuid::fromHexToBytes($token)]
        );

        if (!$parameter) {
            return [];
        }

        return array_filter(json_decode($parameter, true));
    }
}
