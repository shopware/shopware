<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
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

    public function save(string $token, array $parameters, ?string $customerId = null): void
    {
        if (Feature::isActive('FEATURE_NEXT_10058')) {
            $existing = $this->load($token, $customerId);

            $parameters = array_replace_recursive($existing, $parameters);

            unset($parameters['token']);

            $this->connection->executeUpdate(
                'REPLACE INTO sales_channel_api_context (`token`, `payload`, `customer_id`) VALUES (:token, :payload, :customerId)',
                [
                    'token' => $token,
                    'payload' => json_encode($parameters),
                    'customerId' => $customerId ? Uuid::fromHexToBytes($customerId) : null,
                ]
            );

            return;
        }

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

    public function load(string $token/*, ?string $customerId* = null*/): array
    {
        if (Feature::isActive('FEATURE_NEXT_10058')) {
            if (func_num_args() === 2) {
                $customerId = func_get_arg(1);

                $data = $this->connection->fetchAll('SELECT * FROM sales_channel_api_context WHERE customer_id = :customerId OR token = :token LIMIT 2', [
                    'customerId' => $customerId ? Uuid::fromHexToBytes($customerId) : null,
                    'token' => $token,
                ]);

                if (empty($data)) {
                    return [];
                }

                $customerContext = $customerId ? $this->getCustomerContext($data, $customerId) : null;

                $context = $customerContext ?? array_shift($data);

                $payload = array_filter(json_decode($context['payload'], true));
                $payload['token'] = $context['token'];

                return $payload;
            }
        }

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

        if (Feature::isActive('FEATURE_NEXT_10058')) {
            $qb->set('customer_id', 'NULL');
        }

        // keep tokens valid, which are given in $preserveTokens
        if ($preserveTokens) {
            $qb
                ->andWhere($qb->expr()->notIn('token', ':preserveTokens'))
                ->setParameter(':preserveTokens', $preserveTokens, Connection::PARAM_STR_ARRAY);
        }

        $qb->execute();
    }

    private function getCustomerContext(array $data, string $customerId): ?array
    {
        foreach ($data as $row) {
            if (!empty($row['customer_id']) && Uuid::fromBytesToHex($row['customer_id']) === $customerId) {
                return $row;
            }
        }

        return null;
    }
}
