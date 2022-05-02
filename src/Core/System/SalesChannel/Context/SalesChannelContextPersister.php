<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SalesChannelContextPersister
{
    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    private string $lifetimeInterval;

    /**
     * @deprecated tag:v6.5.0 - CartPersisterInterface will be removed, type hint with AbstractCartPersister
     */
    private CartPersisterInterface $cartPersister;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        CartPersisterInterface $cartPersister,      // @deprecated tag:v6.5.0 - CartPersisterInterface will be removed, type hint with AbstractCartPersister
        ?string $lifetimeInterval = 'P1D'
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->lifetimeInterval = $lifetimeInterval ?? 'P1D';

        if (!$cartPersister instanceof AbstractCartPersister) {
            Feature::triggerDeprecationOrThrow(
                'CartPersister parameter in SalesChannelContextPersister::__construct needs to be instance of AbstractCartPersister in v6.5.0.0',
                'v6.5.0.0'
            );
        }
        $this->cartPersister = $cartPersister;
    }

    public function save(string $token, array $newParameters, string $salesChannelId, ?string $customerId = null): void
    {
        $existing = $this->load($token, $salesChannelId, $customerId);

        $parameters = array_replace_recursive($existing, $newParameters);
        if (isset($newParameters['permissions']) && $newParameters['permissions'] === []) {
            $parameters['permissions'] = [];
        }

        unset($parameters['token']);

        $this->connection->executeUpdate(
            'REPLACE INTO sales_channel_api_context (`token`, `payload`, `sales_channel_id`, `customer_id`, `updated_at`)
                VALUES (:token, :payload, :salesChannelId, :customerId, :updatedAt)',
            [
                'token' => $token,
                'payload' => json_encode($parameters),
                'salesChannelId' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
                'customerId' => $customerId ? Uuid::fromHexToBytes($customerId) : null,
                'updatedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    /**
     * @deprecated tag:v6.5.0 - parameter $salesChannelId will be required
     */
    public function delete(string $token, ?string $salesChannelId = null, ?string $customerId = null): void
    {
        if ($salesChannelId === null) {
            Feature::triggerDeprecationOrThrow(
                'Parameter `$salesChannelId` in `SalesChannelContextPersister::delete` will be required with v6.5.0.0',
                'v6.5.0.0'
            );
        }

        $this->connection->executeUpdate(
            'DELETE FROM sales_channel_api_context WHERE token = :token',
            [
                'token' => $token,
            ]
        );
    }

    public function replace(string $oldToken, SalesChannelContext $context): string
    {
        $newToken = Random::getAlphanumericString(32);

        $affected = $this->connection->executeUpdate(
            'UPDATE `sales_channel_api_context`
                   SET `token` = :newToken,
                       `updated_at` = :updatedAt
                   WHERE `token` = :oldToken',
            [
                'newToken' => $newToken,
                'oldToken' => $oldToken,
                'updatedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        if ($affected === 0) {
            $customer = $context->getCustomer();

            $this->connection->insert('sales_channel_api_context', [
                'token' => $newToken,
                'payload' => json_encode([]),
                'sales_channel_id' => Uuid::fromHexToBytes($context->getSalesChannel()->getId()),
                'customer_id' => $customer ? Uuid::fromHexToBytes($customer->getId()) : null,
                'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // @deprecated tag:v6.5.0 - Remove else part
        if ($this->cartPersister instanceof AbstractCartPersister) {
            $this->cartPersister->replace($oldToken, $newToken, $context);
        } else {
            $this->connection->executeUpdate('UPDATE `cart` SET `token` = :newToken WHERE `token` = :oldToken', ['newToken' => $newToken, 'oldToken' => $oldToken]);
        }

        $context->assign(['token' => $newToken]);
        $this->eventDispatcher->dispatch(new SalesChannelContextTokenChangeEvent($context, $oldToken, $newToken));

        return $newToken;
    }

    public function load(string $token, string $salesChannelId, ?string $customerId = null): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*');
        $qb->from('sales_channel_api_context');

        $qb->where('sales_channel_id = :salesChannelId');
        $qb->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));

        if ($customerId !== null) {
            $qb->andWhere('(token = :token OR customer_id = :customerId)');
            $qb->setParameter('token', $token);
            $qb->setParameter('customerId', Uuid::fromHexToBytes($customerId));
            $qb->setMaxResults(2);
        } else {
            $qb->andWhere('token = :token');
            $qb->setParameter('token', $token);
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

        $updatedAt = new \DateTimeImmutable($context['updated_at']);
        $expiredTime = $updatedAt->add(new \DateInterval($this->lifetimeInterval));

        $payload = array_filter(json_decode($context['payload'], true));
        $now = new \DateTimeImmutable();
        $payload['expired'] = $expiredTime < $now;

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
            ->set('updated_at', ':updatedAt')
            ->where('customer_id = :customerId')
            ->setParameter('updatedAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->setParameter('payload', json_encode($revokeParams))
            ->setParameter('customerId', Uuid::fromHexToBytes($customerId));

        // keep tokens valid, which are given in $preserveTokens
        if ($preserveTokens) {
            $qb
                ->andWhere($qb->expr()->notIn('token', ':preserveTokens'))
                ->setParameter('preserveTokens', $preserveTokens, Connection::PARAM_STR_ARRAY);
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
