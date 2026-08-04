<?php declare(strict_types=1);

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\TestNavigationSeoUrlRoute;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopware\Core\Framework\Test\Telemetry\Factory\TraceableTransportFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Tests\Integration\Core\Content\Seo\SalesChannel\FixturesPhp\StoreApiSeoResolverTestRoute;
use Shopware\Tests\Integration\Core\Framework\Api\EventListener\FixturesPhp\SalesChannelAuthenticationListenerTestRoute;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntity;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityAgg;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithHydrator;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithInheritance;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithSearchRanking;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\DummyHydrator;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Version\CalculatedPriceFieldTestDefinition;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestConstantTaxRateProvider;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestEmptyTaxProvider;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestGenericExceptionTaxProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('shopware.messenger.enforce_message_size', true);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autoconfigure();

    $services->set(SalesChannelAuthenticationListenerTestRoute::class)
        ->tag('controller.service_arguments');

    $services->set(StoreApiSeoResolverTestRoute::class)
        ->args([
            service(CategoryRoute::class),
            service(SalesChannelContextFactory::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(CalculatedPriceFieldTestDefinition::class)
        ->tag('shopware.entity.definition');

    // Payment
    $services->set(TestConstantTaxRateProvider::class)
        ->tag('shopware.tax.provider');

    $services->set(TestEmptyTaxProvider::class)
        ->tag('shopware.tax.provider');

    $services->set(TestGenericExceptionTaxProvider::class)
        ->tag('shopware.tax.provider');

    // Route
    $services->set(TestNavigationSeoUrlRoute::class)
        ->args([
            service(CategoryDefinition::class),
        ])
        ->tag('shopware.seo_url.route');

    $services->set(TestProductSeoUrlRoute::class)
        ->args([
            service(ProductDefinition::class),
        ])
        ->tag('shopware.seo_url.route');

    $services->set(AttributeEntity::class)
        ->tag('shopware.entity');

    $services->set(AttributeEntityAgg::class);

    $services->set(AttributeEntityWithHydrator::class)
        ->tag('shopware.entity');

    $services->set(AttributeEntityWithInheritance::class)
        ->tag('shopware.entity');

    $services->set(AttributeEntityWithSearchRanking::class)
        ->tag('shopware.entity');

    $services->set(DummyHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(AppFixture::class)
        ->public()
        ->args([
            service('app.repository'),
        ]);

    $services->set(TraceableTransportFactory::class)
        ->tag('shopware.metric_transport_factory');

    $services->set(TransportCollection::class)
        ->lazy()
        ->factory([TransportCollection::class, 'create'])
        ->args([
            iterator([
                service(TraceableTransportFactory::class),
            ]),
            service(TransportConfigProvider::class),
        ]);
};
