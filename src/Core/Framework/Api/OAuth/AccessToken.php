<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AccessToken implements AccessTokenEntityInterface
{
    use EntityTrait;
    use RefreshTokenTrait;
    use AccessTokenTrait;

    /**
     * @internal
     *
     * @param string $userIdentifier
     * @param ScopeEntityInterface[] $scopes
     */
    public function __construct(
        private ClientEntityInterface $client,
        private array $scopes,
        private $userIdentifier = null
    ) {
    }

    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    public function getUserIdentifier(): string|int|null
    {
        return $this->userIdentifier;
    }

    /**
     * @return ScopeEntityInterface[]
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * Set the identifier of the user associated with the token.
     *
     * @param string|int|null $identifier The identifier of the user
     */
    public function setUserIdentifier($identifier): void
    {
        $this->userIdentifier = $identifier;
    }

    /**
     * Set the client that the token was issued to.
     */
    public function setClient(ClientEntityInterface $client): void
    {
        $this->client = $client;
    }

    /**
     * Associate a scope with the token.
     */
    public function addScope(ScopeEntityInterface $scope): void
    {
        $this->scopes[] = $scope;
    }
}
