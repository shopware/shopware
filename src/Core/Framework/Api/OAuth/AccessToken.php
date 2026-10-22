<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Lcobucci\JWT\Token;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\Clock;

/**
 * OAuth integrations should rely on {@see AccessTokenEntityInterface} instead of this concrete Shopware class.
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class AccessToken implements AccessTokenEntityInterface
{
    use AccessTokenTrait;
    use EntityTrait;
    use RefreshTokenTrait;

    private ?string $resource = null;

    /**
     * @internal
     *
     * @param ScopeEntityInterface[] $scopes
     * @param non-empty-string|null $userIdentifier
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

    /**
     * @return non-empty-string|null
     */
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
     * @param non-empty-string $identifier The identifier of the user
     */
    public function setUserIdentifier(string $identifier): void
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

    /**
     * @internal
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * Binds the token to an RFC 8707 resource, see {@see OAuthResource}.
     *
     * @internal
     */
    public function setResource(?string $resource): void
    {
        $this->resource = $resource;
    }

    public function initJwtConfiguration(): void
    {
        if (!$this->privateKey instanceof FakeCryptKey) {
            $jwtConfig = JWTConfigurationFactory::createJWTConfiguration();
            $this->privateKey = new FakeCryptKey($jwtConfig);
        }

        $this->jwtConfiguration = $this->privateKey->configuration;
    }

    /**
     * Same claims as {@see AccessTokenTrait::convertToJWT()}; a bound resource is added as a further audience
     * after the client id, so readers taking the first `aud` entry as client id keep working.
     */
    private function convertToJWT(): Token
    {
        $this->initJwtConfiguration();

        $audiences = [$this->getClient()->getIdentifier()];
        if ($this->resource !== null) {
            $audiences[] = $this->resource;
        }

        $now = Clock::get()->now();

        return $this->jwtConfiguration->builder()
            ->permittedFor(...$audiences)
            ->identifiedBy($this->getIdentifier())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getUserIdentifier() ?? $this->getClient()->getIdentifier())
            ->withClaim('scopes', $this->getScopes())
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }
}
