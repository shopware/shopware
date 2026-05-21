<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpClientEntity implements ClientEntityInterface
{
    use ClientTrait;
    use EntityTrait;

    private string $salesChannelId = '';

    /**
     * @var list<string>
     */
    private array $allowedScopes = [];

    private ?string $platformProfileUri = null;

    /**
     * @param non-empty-string $identifier
     */
    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @param list<string> $redirectUris
     */
    public function setRedirectUri(array|string $redirectUris): void
    {
        $this->redirectUri = $redirectUris;
    }

    public function setConfidential(bool $confidential): void
    {
        $this->isConfidential = $confidential;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    /**
     * @return list<string>
     */
    public function getAllowedScopes(): array
    {
        return $this->allowedScopes;
    }

    /**
     * @param list<string> $allowedScopes
     */
    public function setAllowedScopes(array $allowedScopes): void
    {
        $this->allowedScopes = $allowedScopes;
    }

    public function getPlatformProfileUri(): ?string
    {
        return $this->platformProfileUri;
    }

    public function setPlatformProfileUri(?string $uri): void
    {
        $this->platformProfileUri = $uri;
    }
}
