<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Cart;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\AbstractUcpCapability;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[Package('framework')]
class CartCapability extends AbstractUcpCapability
{
    public const NAME = 'dev.ucp.shopping.cart';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSpecUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/specification/cart';
    }

    public function getSchemaUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/schemas/shopping/cart.json';
    }
}
