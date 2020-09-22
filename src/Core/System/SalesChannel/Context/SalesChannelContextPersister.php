<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SalesChannelContextPersister
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(Connection $connection, EventDispatcherInterface $eventDispatcher)
    {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function save(string $token, array $parameters): void
    {
        $existing = $this->load($token);

        $parameters = array_replace_recursive($existing, $parameters);

        $this->connection->executeUpdate(
            'REPLACE INTO sales_channel_api_context (`token`, `payload`) VALUES (:token, :payload)',
            [
                'token' => $token,
                'payload' => json_encode($parameters),
            ]
        );
    }

    public function delete(string $token): void
    {
        $this->connection->executeUpdate(
            'DELETE FROM sales_channel_api_context WHERE token = :token',
            [
                'token' => $token,
            ]
        );
    }

    public function replace(string $oldToken/*, ?SalesChannelContext $context = null*/): string
    {
        $newToken = Random::getAlphanumericString(32);

        $affected = $this->connection->executeUpdate(
            'UPDATE `sales_channel_api_context`
                   SET `token` = :newToken
                   WHERE `token` = :oldToken',
            [
                'newToken' => $newToken,
                'oldToken' => $oldToken,
            ]
        );

        if ($affected === 0) {
            $this->connection->insert('sales_channel_api_context', [
                'token' => $newToken,
                'payload' => json_encode([]),
            ]);
        }

        $this->connection->executeUpdate(
            'UPDATE `cart`
                   SET `token` = :newToken
                   WHERE `token` = :oldToken',
            [
                'newToken' => $newToken,
                'oldToken' => $oldToken,
            ]
        );

        // @deprecated tag:v6.4.0.0 - $context will be required
        if (func_num_args() === 2) {
            $context = func_get_arg(1);
            $context->assign(['token' => $newToken]);
            $this->eventDispatcher->dispatch(new SalesChannelContextTokenChangeEvent($context, $oldToken, $newToken));
        }

        return $newToken;
    }

    public function load(string $token): array
    {
        $parameter = $this->connection->fetchColumn(
            'SELECT `payload` FROM sales_channel_api_context WHERE token = :token',
            ['token' => $token]
        );

        if (!$parameter) {
            return [];
        }

        return array_filter(json_decode($parameter, true));
    }

    public function revokeAllCustomerTokens(string $customerId, string ...$preserveTokens): void
    {
        $revokeParams = [
            'customerId' => null,
            'billingAddressId' => null,
            'shippingAddressId' => null,
        ];

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->update('sales_channel_api_context')
            ->set('payload', ':payload')
            ->where('JSON_EXTRACT(payload, :customerPath) = :customerId')
            ->setParameter(':payload', json_encode($revokeParams))
            ->setParameter(':customerPath', '$.customerId')
            ->setParameter(':customerId', $customerId);

        // keep tokens valid, which are given in $preserveTokens
        if ($preserveTokens) {
            $qb
                ->andWhere($qb->expr()->notIn('token', ':preserveTokens'))
                ->setParameter(':preserveTokens', $preserveTokens, Connection::PARAM_STR_ARRAY);
        }

        $qb->execute();
    }
}
