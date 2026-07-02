<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminPrimaryGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminSecondFactorGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope;
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
        if ($this->isMfaMarkerScope($identifier)) {
            return new MfaPendingScope($identifier);
        }

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

        // A completed admin-auth login (primary factor without MFA, or the finishing second factor)
        // must receive exactly the same scopes as a core password login.
        $isPasswordEquivalent = $grantType === self::PASSWORD_GRANT
            || $grantType === AdminPrimaryGrant::TYPE
            || $grantType === AdminSecondFactorGrant::TYPE;

        if ($isPasswordEquivalent) {
            $hasWrite = true;
        }

        if (!$isPasswordEquivalent) {
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

    /**
     * MFA scopes carried by an admin-auth pending token: the static `admin-mfa-pending` marker plus
     * the dynamic `admin-mfa-challenge:<id>` / `admin-mfa-methods:<csv>` markers. They must resolve to
     * a scope entity (instead of failing the lookup) so the pending token can be validated again on
     * the second-factor token request.
     *
     * @phpstan-assert-if-true non-empty-string $identifier
     */
    private function isMfaMarkerScope(string $identifier): bool
    {
        return $identifier === MfaPendingScope::IDENTIFIER
            || str_starts_with($identifier, AdminPrimaryGrant::CHALLENGE_SCOPE_PREFIX)
            || str_starts_with($identifier, AdminPrimaryGrant::METHODS_SCOPE_PREFIX);
    }
}
