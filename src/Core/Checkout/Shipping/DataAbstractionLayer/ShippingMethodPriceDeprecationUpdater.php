<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

/**
 * @deprecated tag:v6.4.0 - deprecated since 6.3.0 will be removed in 6.4.0
 */
class ShippingMethodPriceDeprecationUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function updateByEvent(EntityWrittenEvent $event): void
    {
        //deprecated
    }

    public function updateByShippingMethodId(array $shippingMethodIds): void
    {
        //deprecated
    }
}
