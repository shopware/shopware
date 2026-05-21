<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Fulfillment;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\AbstractUcpCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[Package('framework')]
class FulfillmentExtension extends AbstractUcpCapability
{
    public const NAME = 'dev.ucp.shopping.fulfillment';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSpecUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/specification/fulfillment';
    }

    public function getSchemaUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/schemas/shopping/fulfillment.json';
    }

    public function getExtends(): string|array|null
    {
        return CheckoutCapability::NAME;
    }
}
