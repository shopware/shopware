<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipientTag\NewsletterRecipientTagDefinition;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexer;
use Shopware\Core\Content\Newsletter\Event\Subscriber\NewsletterRecipientDeletedSubscriber;
use Shopware\Core\Content\Newsletter\NewsletterExceptionHandler;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\SalesChannelNewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\ScheduledTask\NewsletterRecipientTask;
use Shopware\Core\Content\Newsletter\ScheduledTask\NewsletterRecipientTaskHandler;
use Shopware\Core\Content\Newsletter\Subscriber\NewsletterRecipientSalutationSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(NewsletterRecipientDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelNewsletterRecipientDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(NewsletterRecipientTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(NewsletterRecipientTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(NewsletterRecipientTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('newsletter_recipient.repository'),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(NewsletterExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(NewsletterSubscribeRoute::class)
        ->public()
        ->args([
            service('newsletter_recipient.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service('shopware.rate_limiter'),
            service(RequestStack::class),
            service(StoreApiCustomFieldMapper::class),
            service('customer.repository'),
        ]);

    $services->set(NewsletterConfirmRoute::class)
        ->public()
        ->args([
            service('newsletter_recipient.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);

    $services->set(NewsletterUnsubscribeRoute::class)
        ->public()
        ->args([
            service('newsletter_recipient.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(RateLimiter::class),
            service(RequestStack::class),
        ]);

    $services->set(NewsletterRecipientIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('newsletter_recipient.repository'),
            service(CustomerNewsletterSalesChannelsUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(CustomerNewsletterSalesChannelsUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(NewsletterRecipientDeletedSubscriber::class)
        ->args([
            service('messenger.default_bus'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(NewsletterRecipientSalutationSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');
};
