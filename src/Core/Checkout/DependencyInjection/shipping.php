<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceExceptionHandler;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTag\ShippingMethodTagDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Checkout\Shipping\Api\ShippingMethodTechnicalNameFkResolver;
use Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader\ShippingMethodDataLoader;
use Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader\ShippingMethodLoaderConfigSerializer;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\SalesChannelShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\Validator\ShippingMethodValidator;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ShippingMethodPriceExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(ShippingMethodDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelShippingMethodDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(ShippingMethodTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ShippingMethodPriceDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ShippingMethodTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ShippingMethodRoute::class)
        ->public()
        ->args([
            service('sales_channel.shipping_method.repository'),
            service(CacheTagCollector::class),
            service(ScriptExecutor::class),
            service(RuleIdMatcher::class),
        ]);

    $services->set(ShippingMethodValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ShippingMethodTechnicalNameFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.sync.fk_resolver');

    // Content System
    $services->alias(AbstractShippingMethodRoute::class, ShippingMethodRoute::class);

    $services->set(ShippingMethodDataLoader::class)
        ->args([
            service(AbstractShippingMethodRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(ShippingMethodLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');
};
