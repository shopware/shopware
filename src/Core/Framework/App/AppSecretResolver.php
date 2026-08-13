<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves an app's current secret by name, read fresh on each call so a rotation
 * (AppSecretRotationService) is used immediately instead of a stale value.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\App\AppSecretResolverTest
 */
#[Package('framework')]
readonly class AppSecretResolver
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function resolve(string $appName): ?string
    {
        $secret = $this->connection->fetchOne('SELECT `app_secret` FROM `app` WHERE `name` = :name', ['name' => $appName]);

        return \is_string($secret) ? $secret : null;
    }

    /**
     * @param list<string> $appNames
     *
     * @return array<string, string>
     */
    public function resolveMany(array $appNames): array
    {
        if ($appNames === []) {
            return [];
        }

        return $this->connection->fetchAllKeyValue(
            'SELECT `name`, `app_secret` FROM `app` WHERE `name` IN (:names) AND `app_secret` IS NOT NULL',
            ['names' => $appNames],
            ['names' => ArrayParameterType::STRING]
        );
    }
}
