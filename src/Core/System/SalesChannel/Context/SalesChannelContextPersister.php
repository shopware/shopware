<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

    /*
     * @deprecated tag:v6.4.0 - $salesChannelId will be required
     */
    public function save(string $token, array $parameters, ?string $salesChannelId = null, ?string $customerId = null): void
    {
        $existing = $this->load($token, $salesChannelId, $customerId);

        $parameters = array_replace_recursive($existing, $parameters);

        unset($parameters['token']);

        $this->connection->executeUpdate(
            'REPLACE INTO sales_channel_api_context (`token`, `payload`, `sales_channel_id`, `customer_id`) VALUES (:token, :payload, :salesChannelId, :customerId)',
            [
                'token' => $token,
                'payload' => json_encode($parameters),
                'salesChannelId' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
                'customerId' => $customerId ? Uuid::fromHexToBytes($customerId) : null,
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

        if ($affected === 0 && \func_num_args() === 2) {
            /** @var SalesChannelContext $context */
            $context = func_get_arg(1);

            $customer = $context->getCustomer();

            $this->connection->insert('sales_channel_api_context', [
                'token' => $newToken,
                'payload' => json_encode([]),
                'sales_channel_id' => Uuid::fromHexToBytes($context->getSalesChannel()->getId()),
                'customer_id' => $customer ? Uuid::fromHexToBytes($customer->getId()) : null,
            ]);
        } elseif ($affected === 0) {
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
        if (\func_num_args() === 2) {
            $context = func_get_arg(1);
            $context->assign(['token' => $newToken]);
            $this->eventDispatcher->dispatch(new SalesChannelContextTokenChangeEvent($context, $oldToken, $newToken));
        }

        return $newToken;
    }

    /*
     * @deprecated tag:v6.4.0 - $salesChannelId will be required
    */
    public function load(string $token, ?string $salesChannelId = null, ?string $customerId = null): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*');
        $qb->from('sales_channel_api_context');

        if ($salesChannelId !== null) {
            $qb->where('sales_channel_id = :salesChannelId');
            $qb->setParameter(':salesChannelId', Uuid::fromHexToBytes($salesChannelId));

            if ($customerId !== null) {
                $qb->andWhere('token = :token OR customer_id = :customerId');
                $qb->setParameter(':token', $token);
                $qb->setParameter(':customerId', Uuid::fromHexToBytes($customerId));
                $qb->setMaxResults(2);
            } else {
                $qb->andWhere('token = :token');
                $qb->setParameter(':token', $token);
                $qb->setMaxResults(1);
            }
        } else {
            $qb->where('token = :token');
            $qb->setParameter(':token', $token);
            $qb->setMaxResults(1);
        }

        /** @var ResultStatement $statement */
        $statement = $qb->execute();

        if (!$statement instanceof ResultStatement) {
            return [];
        }

        $data = $statement->fetchAll();

        if (empty($data)) {
            return [];
        }

        $customerContext = $salesChannelId && $customerId ? $this->getCustomerContext($data, $salesChannelId, $customerId) : null;

        $context = $customerContext ?? array_shift($data);

        $payload = array_filter(json_decode($context['payload'], true));

        if ($customerId) {
            $payload['token'] = $context['token'];
        }

        return $payload;
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
            ->set('customer_id', 'NULL')
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

    private function getCustomerContext(array $data, string $salesChannelId, string $customerId): ?array
    {
        foreach ($data as $row) {
            if (!empty($row['customer_id'])
                && Uuid::fromBytesToHex($row['sales_channel_id']) === $salesChannelId
                && Uuid::fromBytesToHex($row['customer_id']) === $customerId
            ) {
                return $row;
            }
        }

        return null;
    }
}
