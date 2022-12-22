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
    protected string $customerId;

    protected ?Context $context;

    public function __construct(string $customerId, ?Context $context = null)
    {
        $this->customerId = $customerId;
        $this->context = $context;
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
