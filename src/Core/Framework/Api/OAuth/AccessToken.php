<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AccessToken implements AccessTokenEntityInterface
{
    use AccessTokenTrait;
    use EntityTrait;
    use RefreshTokenTrait;

    /**
     * @internal
     *
     * @param ScopeEntityInterface[] $scopes
     */
    public function __construct(
        private ClientEntityInterface $client,
        private array $scopes,
        private ?string $userIdentifier = null
    ) {
    }

    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    public function getUserIdentifier(): ?string
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
     * @param string $identifier The identifier of the user
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

    public function initJwtConfiguration(): void
    {
        if ($this->privateKey instanceof FakeCryptKey) {
            $this->jwtConfiguration = $this->privateKey->configuration;

            return;
        }

        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The JWT generation should be done using HMAC');

        // tag:v6.7.0.0 - throw an exception when the key is not our FakeCryptKey
        /** @var non-empty-string $contents */
        $contents = $this->privateKey->getKeyContents();
        $this->jwtConfiguration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($contents, $this->privateKey->getPassPhrase() ?? ''),
            InMemory::plainText('empty', 'empty')
        );
    }
}
