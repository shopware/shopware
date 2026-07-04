<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Provider;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\Log\Package;

/**
 * Builds {@see AdminAuthProvider} structs from `admin_auth_provider` rows (providers managed via
 * the admin UI). The per-provider settings live in the `config` JSON column; the client secret is
 * stored encrypted at rest (see ProviderSecretSubscriber) and decrypted here so the structs
 * uniformly carry the plaintext secret regardless of the configuration source.
 *
 * @internal
 */
#[Package('framework')]
class DalProviderLoader
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SecretEncryptor $secretEncryptor,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<AdminAuthProvider> all OIDC providers, including inactive ones
     */
    public function load(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, `name`, `active`, `config`
             FROM `admin_auth_provider`
             WHERE `type` = :type
             ORDER BY `priority` DESC, `name` ASC',
            ['type' => 'oidc']
        );

        $providers = [];

        foreach ($rows as $row) {
            $provider = $this->map($row);
            if ($provider !== null) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): ?AdminAuthProvider
    {
        $id = (string) $row['id'];
        $config = json_decode((string) ($row['config'] ?? ''), true);
        if (!\is_array($config)) {
            $config = [];
        }

        $clientSecret = '';
        $storedSecret = $config['clientSecret'] ?? null;
        if (\is_string($storedSecret) && $storedSecret !== '') {
            try {
                $clientSecret = $this->secretEncryptor->decrypt($storedSecret);
            } catch (\Throwable $exception) {
                $this->logger->warning('Skipping admin auth provider "{name}": stored client secret could not be decrypted.', [
                    'name' => (string) $row['name'],
                    'exception' => $exception,
                ]);

                return null;
            }
        }

        return new AdminAuthProvider(
            id: $id,
            providerKey: $id,
            label: (string) $row['name'],
            clientId: $this->string($config['clientId'] ?? null) ?? '',
            clientSecret: $clientSecret,
            discoveryUrl: $this->string($config['discoveryUrl'] ?? null),
            issuer: $this->string($config['issuer'] ?? null),
            authorizationEndpoint: $this->string($config['authorizationEndpoint'] ?? null),
            tokenEndpoint: $this->string($config['tokenEndpoint'] ?? null),
            jwksUri: $this->string($config['jwksUri'] ?? null),
            scopes: $this->scopes($config['scopes'] ?? null),
            autoProvision: (bool) ($config['autoProvision'] ?? false),
            groupsClaim: $this->string($config['groupsClaim'] ?? null),
            roleMapping: $this->roleMapping($config['roleMapping'] ?? null),
            defaultRoles: $this->stringList($config['defaultRoles'] ?? null),
            active: (bool) $row['active'],
        );
    }

    private function string(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The admin UI stores scopes either as a list or as a space-separated string.
     *
     * @return list<string>
     */
    private function scopes(mixed $value): array
    {
        if (\is_string($value)) {
            $value = explode(' ', $value);
        }

        return $this->stringList($value) ?: ['openid', 'profile', 'email'];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): ?string => $this->string($item),
            $value
        )));
    }

    /**
     * @return array<string, list<string>>
     */
    private function roleMapping(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $mapping = [];
        foreach ($value as $group => $roles) {
            $mapping[(string) $group] = $this->stringList($roles);
        }

        return $mapping;
    }
}
