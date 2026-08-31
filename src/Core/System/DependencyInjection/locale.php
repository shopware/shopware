<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\Language\LanguageLoader;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\Core\System\Locale\Api\LocaleCodeFkResolver;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Locale\Subscriber\LocaleValidator;
use Shopware\Core\System\Locale\SystemCheck\LocalesReadinessCheck;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LocaleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(LocaleTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(LanguageLocaleCodeProvider::class)
        ->args([
            service(LanguageLoader::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(LocaleValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(LocalesReadinessCheck::class)
        ->args([
            service('locale.repository'),
        ])
        ->tag('shopware.system_check');

    $services->set(LocaleCodeFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.sync.fk_resolver');
};
