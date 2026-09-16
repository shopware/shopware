<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\SalesChannelContextAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('after-sales')]
class SalesChannelContextAwareEvent implements FlowEventAware, SalesChannelContextAware
{
    public function __construct(
        private readonly string $salesChannelId,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }
}
