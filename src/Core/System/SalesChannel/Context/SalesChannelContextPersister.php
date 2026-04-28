<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-import-type SalesChannelContextFactoryPrimitiveOptions from AbstractSalesChannelContextFactory
 *
 * @phpstan-type SalesChannelContextPayload SalesChannelContextFactoryPrimitiveOptions&array{additional?: ?SalesChannelContextFactoryPrimitiveOptions}
 * @phpstan-type SalesChannelContextLoadPayload SalesChannelContextPayload&array{token: string, cartToken: string, expired: bool}
 * @phpstan-type SalesChannelContextDbRow array{token: string, cart_token: string, payload: ?string, customer_id: ?string, updated_at: string, additional_payload: ?string}
 */
#[Package('framework')]
class SalesChannelContextPersister
{
    private readonly string $lifetimeInterval;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCartPersister $cartPersister,
        ?string $lifetimeInterval = 'P1D'
    ) {
        $this->lifetimeInterval = $lifetimeInterval ?? 'P1D';
    }

    /**
     * @param SalesChannelContextPayload $newParameters
     */
    public function save(string $token, array $newParameters, string $salesChannelId, ?string $customerId = null): void
    {
        $existing = $this->load($token, $salesChannelId, $customerId);

        $parameters = array_replace_recursive($existing, $newParameters);
        $parameters = $this->cleanupParameters($parameters);

        unset($parameters['token']);
        unset($parameters[SalesChannelContextService::CUSTOMER_ID]);
        unset($parameters['expired']);

        $this->_save($token, $parameters, $salesChannelId, $customerId);
    }

    public function create(string $token, string $salesChannelId, ?string $customerId = null): void
    {
        $this->_save($token, [], $salesChannelId, $customerId);
    }

    public function delete(string $token): void
    {
        $this->connection->executeStatement(
            'DELETE FROM sales_channel_context scc
            WHERE EXISTS (
                SELECT 1
                FROM sales_channel_context_token scct
                WHERE scct.sales_channel_context_id = scc.id
                AND scct.token = :token
            )
        ',
            ['token' => $token]
        );
    }

    public function deleteToken(string $token): void
    {
        $this->connection->executeStatement(
            'DELETE FROM sales_channel_context_token WHERE token = :token',
            [
                'token' => $token,
            ]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement, there is no need to replace a context token, use a new one or reuse the existing one
     */
    public function replace(string $oldToken, SalesChannelContext $context): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'),
        );

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('MULTI_CONTEXT_TOKENS')) {
            return $oldToken;
        }

        $newToken = SalesChannelContextService::getNewToken();

        $affected = $this->connection->executeStatement(
            'UPDATE sales_channel_context_token SET token = :newToken WHERE token = :oldToken',
            [
                'newToken' => $newToken,
                'oldToken' => $oldToken,
            ]
        );

        if ($affected === 0) {
            $customerId = $context->getCustomerId();

            $id = Uuid::randomBytes();

            $this->connection->insert('sales_channel_context', [
                'id' => $id,
                'cart_token' => $newToken,
                'sales_channel_id' => Uuid::fromHexToBytes($context->getSalesChannelId()),
                'customer_id' => $customerId ? Uuid::fromHexToBytes($customerId) : null,
            ]);

            $this->connection->insert('sales_channel_context_token', [
                'token' => $newToken,
                'sales_channel_context_id' => $id,
            ]);
        } else {
            $this->connection->executeStatement(
                'UPDATE sales_channel_context SET cart_token = :cartToken WHERE id = (
                    SELECT sales_channel_context_id FROM sales_channel_context_token WHERE token = :token
                )',
                [
                    'cartToken' => $newToken,
                    'token' => $newToken,
                ]
            );
        }

        $this->cartPersister->replace($oldToken, $newToken, $context);

        $context->assign(['token' => $newToken, 'cartToken' => $newToken]);
        $this->eventDispatcher->dispatch(new SalesChannelContextTokenChangeEvent($context, $oldToken, $newToken));

        return $newToken;
    }

    /**
     * @return SalesChannelContextLoadPayload|array{token: string, cartToken: string, expired: true}|array{}
     */
    public function load(string $token, string $salesChannelId, ?string $customerId = null): array
    {
        $tokenSql = '';
        if ($customerId) {
            $tokenSql = ' AND (scct.token = :token OR scc.customer_id = :customerId) LIMIT 2';
        } else {
            $tokenSql = ' AND scct.token = :token LIMIT 1';
        }

        /** @var list<SalesChannelContextDbRow> */
        $data = $this->connection->fetchAllAssociative(
            'SELECT
                scct.token,
                scc.cart_token,
                scc.payload,
                LOWER(HEX(scc.customer_id)) as customer_id,
                scct.updated_at,
                scct.additional_payload
            FROM sales_channel_context scc
            INNER JOIN sales_channel_context_token scct ON scc.id = scct.sales_channel_context_id
            WHERE scc.sales_channel_id = :salesChannelId' . $tokenSql,
            [
                'token' => $token,
                'customerId' => $customerId ? Uuid::fromHexToBytes($customerId) : null,
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
            ]
        );

        if ($data === []) {
            return [];
        }

        // Get a matching customer context row or use the first one as fallback
        $customerContext = $this->getCustomerContext($data, $customerId) ?? array_shift($data);

        /** @var SalesChannelContextLoadPayload */
        $payload = $customerContext['payload'] ? json_decode((string) $customerContext['payload'], true, 512, \JSON_THROW_ON_ERROR) : [];

        // Check if the context is expired
        $updatedAt = new \DateTimeImmutable($customerContext['updated_at']);
        $expiredTime = $updatedAt->add(new \DateInterval($this->lifetimeInterval));
        $payload['expired'] = $expiredTime < new \DateTimeImmutable();

        // Override payload customerId from context, if available
        if ($customerContext['customer_id']) {
            $payload[SalesChannelContextService::CUSTOMER_ID] = $customerContext['customer_id'];
        }

        // If the context is expired and there is no customer bound to it, we omit all other data
        if ($payload['expired'] && $customerId === null) {
            $payload = ['expired' => true];
        }

        /** @var ?SalesChannelContextLoadPayload */
        $additionalPayload = $customerContext['additional_payload'] ? json_decode((string) $customerContext['additional_payload'], true, 512, \JSON_THROW_ON_ERROR) : null;
        if ($additionalPayload) {
            $payload['additional'] = $additionalPayload;
        }

        $payload['token'] = $customerContext['token'];
        $payload[SalesChannelContextService::CART_TOKEN] = $customerContext['cart_token'];

        return $payload;
    }

    public function revokeAllCustomerTokens(string $customerId, string ...$preserveTokens): void
    {
        if ($customerId === '') {
            return;
        }

        $salesChannelContextIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM sales_channel_context WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );

        if ($salesChannelContextIds === []) {
            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM sales_channel_context_token
            WHERE sales_channel_context_id IN (:salesChannelContextIds)
            ' . ($preserveTokens ? 'AND token NOT IN (:preserveTokens)' : ''),
            [
                'salesChannelContextIds' => $salesChannelContextIds,
                'preserveTokens' => $preserveTokens,
            ],
            [
                'salesChannelContextIds' => ArrayParameterType::BINARY,
                'preserveTokens' => ArrayParameterType::STRING,
            ]
        );
    }

    /**
     * @param SalesChannelContextPayload|array{} $parameters
     */
    private function _save(string $token, array $parameters, string $salesChannelId, ?string $customerId = null): void
    {
        $salesChannelContextId = null;

        if ($customerId) {
            $salesChannelContextId = $this->connection->fetchOne(
                'SELECT id FROM sales_channel_context WHERE customer_id = :customerId AND sales_channel_id = :salesChannelId',
                ['customerId' => Uuid::fromHexToBytes($customerId), 'salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
            );
        } else {
            $salesChannelContextId = $this->connection->fetchOne(
                'SELECT sales_channel_context_id FROM sales_channel_context_token WHERE token = :token',
                ['token' => $token]
            );
        }

        $cartToken = $parameters[SalesChannelContextService::CART_TOKEN] ?? null;
        if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('MULTI_CONTEXT_TOKENS')) {
            // Always use the context token as the cart token to keep existing behavior
            $cartToken = $token;
        }
        // If we already have a sales_channel_context we only want to update the cart token if it is explicitly set or we don't have a context
        $writeCartToken = $cartToken ? true : ($salesChannelContextId ? false : true);
        unset($parameters[SalesChannelContextService::CART_TOKEN]);

        $hasContext = (bool) $salesChannelContextId;
        $salesChannelContextId = $salesChannelContextId ?: Uuid::randomBytes();

        $this->connection->executeStatement(
            'INSERT INTO sales_channel_context (id, cart_token, payload, sales_channel_id, customer_id)
                VALUES (:id, :cartToken, :payload, :salesChannelId, :customerId)
                ON DUPLICATE KEY UPDATE
                    ' . ($writeCartToken ? 'cart_token = :cartToken,' : '') . '
                    payload = :payload,
                    ' . ($customerId ? 'customer_id = :customerId,' : '') . '
                    sales_channel_id = :salesChannelId,
                    updated_at = NOW()',
            [
                'id' => $salesChannelContextId,
                'cartToken' => $cartToken ?? $token,
                'payload' => $parameters ? json_encode($parameters, \JSON_THROW_ON_ERROR) : null,
                'salesChannelId' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
                'customerId' => $customerId ? Uuid::fromHexToBytes($customerId) : null,
            ]
        );

        $additionalPayload = null;
        if (\array_key_exists('additional', $parameters)) {
            $additionalPayload = $parameters['additional'];
            unset($parameters['additional']);
        }

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('MULTI_CONTEXT_TOKENS')) {
            $this->connection->executeStatement(
                'INSERT INTO sales_channel_context_token (token, sales_channel_context_id, additional_payload)
                    VALUES (:token, :salesChannelContextId, :additionalPayload)
                    ON DUPLICATE KEY UPDATE
                        sales_channel_context_id = :salesChannelContextId,
                        additional_payload = :additionalPayload
                    ',
                [
                    'token' => $token,
                    'salesChannelContextId' => $salesChannelContextId,
                    'additionalPayload' => $additionalPayload ? json_encode($additionalPayload, \JSON_THROW_ON_ERROR) : null,
                ]
            );
        } else {
            if ($hasContext) {
                $this->connection->executeStatement(
                    'DELETE FROM sales_channel_context_token WHERE sales_channel_context_id = :salesChannelContextId',
                    ['salesChannelContextId' => $salesChannelContextId]
                );
            }

            $this->connection->executeStatement(
                'REPLACE INTO sales_channel_context_token (token, sales_channel_context_id, additional_payload)
                VALUES (:token, :salesChannelContextId, :additionalPayload)',
                [
                    'token' => $token,
                    'salesChannelContextId' => $salesChannelContextId,
                    'additionalPayload' => $additionalPayload ? json_encode($additionalPayload, \JSON_THROW_ON_ERROR) : null,
                ]
            );
        }
    }

    /**
     * Cleans up the parameters by removing null values and empty arrays to reduce the database size of the payload.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function cleanupParameters(array $parameters): array
    {
        foreach ($parameters as $key => $value) {
            if ($value === null) {
                unset($parameters[$key]);
            } elseif (\is_array($value)) {
                $parameters[$key] = $this->cleanupParameters($value);
                if ($parameters[$key] === []) {
                    unset($parameters[$key]);
                }
            }
        }

        return $parameters;
    }

    /**
     * @param list<SalesChannelContextDbRow> $data
     *
     * @return ?SalesChannelContextDbRow
     */
    private function getCustomerContext(array $data, ?string $customerId): ?array
    {
        foreach ($data as $row) {
            if ($row['customer_id'] !== '' && $customerId !== null && $row['customer_id'] === $customerId) {
                return $row;
            }
        }

        return null;
    }
}
