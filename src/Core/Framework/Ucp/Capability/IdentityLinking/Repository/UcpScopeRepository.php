<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpClientEntity;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpScopeEntity;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Scope\UcpScopeRegistry;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpScopeRepository implements ScopeRepositoryInterface
{
    public function __construct(
        private readonly UcpScopeRegistry $registry,
    ) {
    }

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        if ($identifier === '' || !$this->registry->has($identifier)) {
            return null;
        }

        return new UcpScopeEntity($identifier);
    }

    /**
     * @param ScopeEntityInterface[] $scopes
     *
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        if (!$clientEntity instanceof UcpClientEntity) {
            return $scopes;
        }

        $allowed = $clientEntity->getAllowedScopes();
        if ($allowed === []) {
            return $scopes;
        }

        return array_values(array_filter(
            $scopes,
            static fn (ScopeEntityInterface $s): bool => \in_array($s->getIdentifier(), $allowed, true)
        ));
    }
}
