<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\Scope\AdminScope;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ScopeRepository implements ScopeRepositoryInterface
{
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
    public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface
    {
        return $this->scopes[$identifier] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ): array {
        $hasWrite = false;

        if ($grantType === 'password') {
            $hasWrite = true;
        }

        if ($grantType !== 'password') {
            $scopes = $this->removeScope($scopes, UserVerifiedScope::class);
        }

        if ($grantType === 'client_credentials' && $clientEntity instanceof ApiClient && $clientEntity->getWriteAccess()) {
            $hasWrite = true;
        }

        if (!$hasWrite) {
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

    private function uniqueScopes(array $scopes): array
    {
        $uniqueScopes = [];

        /** @var ScopeEntityInterface $scope */
        foreach ($scopes as $scope) {
            $uniqueScopes[$scope->getIdentifier()] = $scope;
        }

        return array_values($uniqueScopes);
    }

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
