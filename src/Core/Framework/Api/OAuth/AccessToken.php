<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

class AccessToken implements AccessTokenEntityInterface
{
    use EntityTrait;
    use RefreshTokenTrait;
    use AccessTokenTrait;

    /**
     * @var ClientEntityInterface
     */
    private $client;

    /**
     * @var string
     */
    private $userIdentifier;

    /**
     * @var ScopeEntityInterface[]
     */
    private $scopes;

    public function __construct(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $this->client = $clientEntity;
        $this->scopes = $scopes;
        $this->userIdentifier = $userIdentifier;
    }

    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    /**
     * @return string|int
     */
    public function getUserIdentifier()
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
