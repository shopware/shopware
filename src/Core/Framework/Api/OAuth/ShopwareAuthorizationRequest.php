<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Shopware\Core\Framework\Log\Package;

/**
 * Authorization request that also carries the RFC 8707 resource the client asked for.
 *
 * @internal
 */
#[Package('framework')]
class ShopwareAuthorizationRequest extends AuthorizationRequest
{
    private ?string $resource = null;

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(?string $resource): void
    {
        $this->resource = $resource;
    }
}
