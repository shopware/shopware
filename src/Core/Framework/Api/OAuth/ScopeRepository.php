<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;

class ScopeRepository implements ScopeRepositoryInterface
{
    /**
     * @var ScopeEntityInterface[]
     */
    private $scopes;

    /**
     * @param ScopeEntityInterface[] $scopes
     */
    public function __construct(iterable $scopes)
    {
        $scopeIndex = [];
        foreach ($scopes as $scope) {
            $scopeIndex[$scope->getIdentifier()] = $scope;
        }

        $this->scopes = $scopeIndex;
    }

    /**
     * Return information about a scope.
     *
     * @param string $identifier The scope identifier
     *
     * @return ScopeEntityInterface
     */
    public function getScopeEntityByIdentifier($identifier)
    {
        return $this->scopes[$identifier] ?? null;
    }

    /**
     * Given a client, grant type and optional user identifier validate the set of scopes requested are valid and optionally
     * append additional scopes or remove requested scopes.
     *
     * @param ScopeEntityInterface[] $scopes
     * @param string                 $grantType
     * @param ClientEntityInterface  $clientEntity
     * @param string|null            $userIdentifier
     *
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ) {
        $hasWrite = false;

        if ($grantType === 'password') {
            $hasWrite = true;
        }

        if ($grantType === 'client_credentials' && $clientEntity instanceof ApiClient && $clientEntity->getWriteAccess()) {
            $hasWrite = true;
        }

        if (!$hasWrite) {
            foreach ($scopes as $index => $scope) {
                if (!$hasWrite && $scope instanceof WriteScope) {
                    unset($scopes[$index]);
                }
            }
        }

        if ($hasWrite) {
            $scopes[] = new WriteScope();
        }

        return $scopes;
    }
}
