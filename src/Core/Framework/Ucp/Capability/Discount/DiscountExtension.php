<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Discount;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\AbstractUcpCapability;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * UCP discount extension. Extends both Cart and Checkout via the multi-parent
 * mechanism defined in the spec.
 */
#[Package('framework')]
class DiscountExtension extends AbstractUcpCapability
{
    public const NAME = 'dev.ucp.shopping.discount';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSpecUrl(): string
    {
        return 'https://ucp.dev/specification/discount/';
    }

    public function getSchemaUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/schemas/shopping/discount.json';
    }

    public function getExtends(): string|array|null
    {
        return [CartCapability::NAME, CheckoutCapability::NAME];
    }
}
