<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Api;

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
    private $scopes = [];

    public function __construct(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $this->client = $clientEntity;
        $this->scopes = $scopes;
        $this->userIdentifier = $userIdentifier;
    }

    /**
     * @return ClientEntityInterface
     */
    public function getClient()
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
    public function getScopes()
    {
        return $this->scopes;
    }

    /**
     * Set the identifier of the user associated with the token.
     *
     * @param string|int|null $identifier The identifier of the user
     */
    public function setUserIdentifier($identifier)
    {
        $this->userIdentifier = $identifier;
    }

    /**
     * Set the client that the token was issued to.
     *
     * @param ClientEntityInterface $client
     */
    public function setClient(ClientEntityInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Associate a scope with the token.
     *
     * @param ScopeEntityInterface $scope
     */
    public function addScope(ScopeEntityInterface $scope)
    {
        $this->scopes[] = $scope;
    }
}
