<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\System\Language\CachedLanguageLoader;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageExceptionHandler;
use Shopware\Core\System\Language\LanguageLoader;
use Shopware\Core\System\Language\LanguageValidator;
use Shopware\Core\System\Language\Rule\LanguageRule;
use Shopware\Core\System\Language\SalesChannel\LanguageRoute;
use Shopware\Core\System\Language\SalesChannel\SalesChannelLanguageDefinition;
use Shopware\Core\System\Language\SalesChannelLanguageLoader;
use Shopware\Core\System\Language\TranslationValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LanguageDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelLanguageDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(LanguageValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LanguageLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(SalesChannelLanguageLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CachedLanguageLoader::class)
        ->decorate(LanguageLoader::class)
        ->args([
            service(CachedLanguageLoader::class . '.inner'),
            service('cache.object'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TranslationValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LanguageExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(LanguageRoute::class)
        ->public()
        ->args([
            service('sales_channel.language.repository'),
            service(CacheTagCollector::class),
        ]);

    $services->set(LanguageRule::class)
        ->tag('shopware.rule.definition');
};
