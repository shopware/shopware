<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\Aggregate\CountryState\SalesChannel\SalesChannelCountryStateDefinition;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryStateRoute;
use Shopware\Core\System\Country\SalesChannel\SalesChannelCountryDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CountryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelCountryDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(CountryStateDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelCountryStateDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(CountryStateTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CountryTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CountryRoute::class)
        ->public()
        ->args([
            service('sales_channel.country.repository'),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);

    $services->set(CountryStateRoute::class)
        ->public()
        ->args([
            service('country_state.repository'),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);
};
