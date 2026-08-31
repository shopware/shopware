<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Core\System\Currency\Api\CurrencyIsoCodeFkResolver;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\Currency\CurrencyLoadSubscriber;
use Shopware\Core\System\Currency\CurrencyValidator;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\System\Currency\SalesChannel\CurrencyRoute;
use Shopware\Core\System\Currency\SalesChannel\SalesChannelCurrencyDefinition;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CurrencyDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CurrencyCountryRoundingDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelCurrencyDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(CurrencyTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CurrencyLoadSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CurrencyValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(CurrencyRule::class)
        ->tag('shopware.rule.definition');

    $services->set(CurrencyFormatter::class)
        ->public()
        ->args([
            service(LanguageLocaleCodeProvider::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CurrencyRoute::class)
        ->public()
        ->args([
            service('sales_channel.currency.repository'),
            service(CacheTagCollector::class),
        ]);

    $services->set(CurrencyIsoCodeFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.sync.fk_resolver');
};
