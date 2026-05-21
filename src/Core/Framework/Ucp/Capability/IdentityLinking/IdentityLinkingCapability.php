<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\AbstractUcpCapability;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Advertises Shopware's OAuth 2.0 Authorization Server (per Sales Channel)
 * as a UCP identity-linking provider. Scope vocabulary returned in the
 * profile's `config.scopes` matches the UCP-defined operations.
 */
#[Package('framework')]
class IdentityLinkingCapability extends AbstractUcpCapability
{
    public const NAME = 'dev.ucp.common.identity_linking';

    public const SCOPE_CART_MANAGE = 'dev.ucp.shopping.cart:manage';
    public const SCOPE_ORDER_READ = 'dev.ucp.shopping.order:read';
    public const SCOPE_ORDER_MANAGE = 'dev.ucp.shopping.order:manage';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSpecUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/specification/identity-linking';
    }

    public function getSchemaUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/schemas/common/identity_linking.json';
    }

    public function getProfileConfig(): ?array
    {
        return [
            'scopes' => [
                self::SCOPE_CART_MANAGE => new \stdClass(),
                self::SCOPE_ORDER_READ => new \stdClass(),
                self::SCOPE_ORDER_MANAGE => new \stdClass(),
            ],
        ];
    }
}
