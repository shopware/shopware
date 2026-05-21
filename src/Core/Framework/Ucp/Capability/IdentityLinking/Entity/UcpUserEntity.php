<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity;

use League\OAuth2\Server\Entities\UserEntityInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Mapping wrapper around a Shopware customer for the League OAuth2 server.
 *
 * @internal
 */
#[Package('framework')]
class UcpUserEntity implements UserEntityInterface
{
    /**
     * @param non-empty-string $customerId
     */
    public function __construct(
        private readonly string $customerId,
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function getIdentifier(): string
    {
        return $this->customerId;
    }
}
