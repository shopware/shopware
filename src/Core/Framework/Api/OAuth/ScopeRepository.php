<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\Scope\AdminScope;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ScopeRepository implements ScopeRepositoryInterface
{
    /**
     * @internal abstraction on external library
     *
     * @see PasswordGrant::getIdentifier()
     */
    public const PASSWORD_GRANT = 'password';

    /**
     * @internal abstraction on external library
     *
     * @see ClientCredentialsGrant::getIdentifier()
     */
    public const CLIENT_CREDENTIAL_GRANT = 'client_credentials';

    /**
     * @internal abstraction on external library
     *
     * @see RefreshTokenGrant::getIdentifier()
     */
    public const REFRESH_TOKEN_GRANT = 'refresh_token';

    /**
     * @var ScopeEntityInterface[]
     */
    private readonly array $scopes;

    /**
     * @internal
     *
     * @param ScopeEntityInterface[] $scopes
     */
    public function __construct(
        iterable $scopes,
        private readonly Connection $connection
    ) {
        $scopeIndex = [];
        foreach ($scopes as $scope) {
            $scopeIndex[$scope->getIdentifier()] = $scope;
        }

        $this->scopes = $scopeIndex;
    }

    /**
     * {@inheritdoc}
     */
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return $this->scopes[$identifier] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        $hasWrite = false;

        if ($grantType === self::PASSWORD_GRANT) {
            $hasWrite = true;
        }

        if ($grantType !== self::PASSWORD_GRANT) {
            $scopes = $this->removeScope($scopes, UserVerifiedScope::class);
        }

        if ($grantType === self::CLIENT_CREDENTIAL_GRANT && $clientEntity instanceof ApiClient && $clientEntity->getWriteAccess()) {
            $hasWrite = true;
        }

        if (!$hasWrite && $grantType !== self::REFRESH_TOKEN_GRANT) {
            $scopes = $this->removeScope($scopes, WriteScope::class);
        }

        if ($hasWrite) {
            $scopes[] = new WriteScope();
        }

        $isAdmin = $this->connection->createQueryBuilder()
            ->select('admin')
            ->from('user')
            ->where('id = UNHEX(:accessKey)')
            ->setParameter('accessKey', $userIdentifier)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($isAdmin) {
            $scopes[] = new AdminScope();
        }

        return $this->uniqueScopes($scopes);
    }

    /**
     * @param ScopeEntityInterface[] $scopes
     *
     * @return ScopeEntityInterface[]
     */
    private function uniqueScopes(array $scopes): array
    {
        $uniqueScopes = [];

        foreach ($scopes as $scope) {
            $uniqueScopes[$scope->getIdentifier()] = $scope;
        }

        return array_values($uniqueScopes);
    }

    /**
     * @param ScopeEntityInterface[] $scopes
     * @param class-string<ScopeEntityInterface> $class
     *
     * @return ScopeEntityInterface[]
     */
    private function removeScope(array $scopes, string $class): array
    {
        foreach ($scopes as $index => $scope) {
            if ($scope instanceof $class) {
                unset($scopes[$index]);
            }
        }

        return $scopes;
    }
}
