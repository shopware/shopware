<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * Holds the context token a handoff token refers to, keyed by the `jti` of that handoff token.
 *
 * Entries are single use: {@see self::consume()} removes the entry it returns, so the context
 * token stops existing in the database as soon as it was handed over.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\System\SalesChannel\Context\ContextHandoffTokenStoreTest
 */
#[Package('framework')]
class ContextHandoffTokenStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function store(string $jti, string $contextToken, \DateTimeInterface $expiresAt): void
    {
        $this->connection->insert('context_handoff_token', [
            'token' => $jti,
            'context_token' => $contextToken,
            'expires' => $expiresAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function consume(string $jti): ?string
    {
        $contextToken = $this->connection->fetchOne(
            'SELECT context_token FROM context_handoff_token WHERE token = :token AND expires > :now',
            ['token' => $jti, 'now' => $this->now()]
        );

        if (!\is_string($contextToken) || $contextToken === '') {
            return null;
        }

        // concurrent redemptions race on this delete, only the one that removed the row may proceed
        $deleted = (int) $this->connection->delete('context_handoff_token', ['token' => $jti]);

        return $deleted === 1 ? $contextToken : null;
    }

    public function deleteExpired(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM context_handoff_token WHERE expires <= :now',
            ['now' => $this->now()]
        );
    }

    private function now(): string
    {
        return $this->clock->now()
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
