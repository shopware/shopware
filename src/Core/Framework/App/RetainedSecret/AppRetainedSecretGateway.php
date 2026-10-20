<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\RetainedSecret;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\App\RetainedSecret\AppRetainedSecretGatewayTest
 */
#[Package('framework')]
readonly class AppRetainedSecretGateway
{
    private const RETENTION = '72 hours';

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Keeps every secret an app of this shop may hold, so a shop that later loses this ShopId can hand them to
     * the shop that still owns the app's registration.
     */
    public function retainCandidates(string $shopId): void
    {
        $apps = $this->connection->fetchAllAssociative('SELECT name, app_secret, unconfirmed_app_secrets FROM app WHERE app_secret IS NOT NULL');
        $expiresAt = $this->clock->now()->modify('+' . self::RETENTION)->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($apps as $app) {
            /** @var list<string> $unconfirmedSecrets */
            $unconfirmedSecrets = \is_string($app['unconfirmed_app_secrets'])
                ? json_decode($app['unconfirmed_app_secrets'], true, 512, \JSON_THROW_ON_ERROR)
                : [];

            foreach ([...$unconfirmedSecrets, $app['app_secret']] as $secret) {
                $this->connection->executeStatement('
                    INSERT INTO app_retained_secret (shop_id, app_name, secret, expires_at)
                    VALUES (:shop_id, :app_name, :secret, :expires_at)
                    ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)
                ', [
                    'shop_id' => $shopId,
                    'app_name' => $app['name'],
                    'secret' => $secret,
                    'expires_at' => $expiresAt,
                ]);
            }
        }

        $this->purgeExpired();
    }

    /**
     * @return list<array{shopId: string, secret: string, expiresAt: \DateTimeImmutable}>
     */
    public function findValid(string $appName): array
    {
        $this->purgeExpired();

        $rows = $this->connection->fetchAllAssociative(
            'SELECT shop_id, secret, expires_at FROM app_retained_secret WHERE app_name = :app_name ORDER BY expires_at DESC',
            ['app_name' => $appName]
        );

        return array_map(static fn (array $row): array => [
            'shopId' => (string) $row['shop_id'],
            'secret' => (string) $row['secret'],
            'expiresAt' => new \DateTimeImmutable((string) $row['expires_at']),
        ], $rows);
    }

    public function purgeExpired(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM app_retained_secret WHERE expires_at < :now',
            ['now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );
    }
}
