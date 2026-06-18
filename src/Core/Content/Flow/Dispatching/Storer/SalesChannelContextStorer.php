<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelContextAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('after-sales')]
class SalesChannelContextStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractSalesChannelContextFactory $factory)
    {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof SalesChannelContextAware) {
            return $stored;
        }

        $stored[MailAware::SALES_CHANNEL_ID] = $event->getSalesChannelId();

        if ($event->getSalesChannelContext()->getDomainId()) {
            $stored[SalesChannelContextAware::SALES_CHANNEL_DOMAIN_ID] = $event->getSalesChannelContext()->getDomainId();
        }

        if ($event->getSalesChannelContext()->getCustomerId()) {
            $stored[SalesChannelContextAware::SALES_CHANNEL_CUSTOMER_ID] = $event->getSalesChannelContext()->getCustomerId();
        }

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (
            !$storable->hasStore(MailAware::SALES_CHANNEL_ID)
            || $storable->hasStore(SalesChannelContextAware::SALES_CHANNEL_CUSTOMER_ID)
        ) {
            return;
        }

        $storable->lazy(
            SalesChannelContextAware::SALES_CHANNEL_CONTEXT,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?SalesChannelContext
    {
        $salesChannelId = $storableFlow->getStore(MailAware::SALES_CHANNEL_ID);
        if (!\is_string($salesChannelId)) {
            return null;
        }
        $domainId = $storableFlow->getStore(SalesChannelContextAware::SALES_CHANNEL_DOMAIN_ID);
        $context = $storableFlow->getContext();

        return $this->factory->create(
            Uuid::randomHex(),
            $salesChannelId,
            [
                SalesChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
                SalesChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
                SalesChannelContextService::DOMAIN_ID => $domainId,
            ]
        );
    }
}
