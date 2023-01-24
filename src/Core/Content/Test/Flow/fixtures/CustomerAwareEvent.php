<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\fixtures;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;

/**
 * @package business-ops
 *
 * @internal
 */
class CustomerAwareEvent implements CustomerAware
{
    public function __construct(protected string $customerId, protected ?Context $context = null)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return 'customer.aware.event';
    }

    public function getContext(): Context
    {
        return $this->context ?? Context::createDefaultContext();
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }
}
