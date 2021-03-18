<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Tax\TaxDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedSalesChannelContextFactoryInvalidator implements EventSubscriberInterface
{
    private CacheInvalidationLogger $logger;

    public function __construct(
        CacheInvalidationLogger $logger
    ) {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['invalidate', 2000],
            ],
        ];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys(SalesChannelDefinition::ENTITY_NAME);
        $keys = array_map([CachedSalesChannelContextFactory::class, 'buildName'], $ids);

        if ($event->getEventByEntityName(CurrencyDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(PaymentMethodDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(ShippingMethodDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(TaxDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(CountryDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(CustomerGroupDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(LanguageDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        $keys = array_filter(array_unique($keys));

        if (empty($keys)) {
            return;
        }

        $this->logger->log($keys);
    }
}
