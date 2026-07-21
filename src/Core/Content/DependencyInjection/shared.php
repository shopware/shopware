<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerGroupProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerRecoveryProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\NewsletterRecipientProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderTransactionProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\StateMachineStateProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Mail & Flow Data Providers
    $services->set(OrderProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'order']);

    $services->set(CustomerProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'customer']);

    $services->set(CustomerGroupProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'customer_group']);

    $services->set(ProductProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'product']);

    $services->set(CustomerRecoveryProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'customer_recovery']);

    $services->set(NewsletterRecipientProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'newsletter_recipient']);

    $services->set(OrderTransactionProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'order_transaction']);

    $services->set(SalesChannelProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'sales_channel']);

    $services->set(StateMachineStateProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'state_machine_state']);

    $services->set(UserRecoveryProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('shopware.mail.data_provider', ['key' => 'user_recovery']);
};
