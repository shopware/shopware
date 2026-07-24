<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\DependencyInjection;

use Shopware\Core\DevOps\Docs\App\DocsAppEventCommand;
use Shopware\Core\DevOps\Docs\Script\HooksReferenceGenerator;
use Shopware\Core\DevOps\Docs\Script\ScriptReferenceGeneratorCommand;
use Shopware\Core\DevOps\Docs\Script\ServiceReferenceGenerator;
use Shopware\Core\DevOps\Docs\Script\TriggerReferenceGeneratorCommand;
use Shopware\Core\DevOps\System\Command\OpenApiValidationCommand;
use Shopware\Core\DevOps\System\Command\SyncComposerVersionCommand;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire();

    $services->set(SyncComposerVersionCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(DocsAppEventCommand::class)
        ->args([
            service(BusinessEventCollector::class),
            service(HookableEventCollector::class),
            tagged_iterator('shopware.hookable_event.describer'),
            service('twig'),
        ])
        ->tag('console.command');

    $services->set(ScriptReferenceGeneratorCommand::class)
        ->args([
            tagged_iterator('shopware.scripts_reference.generator'),
        ])
        ->tag('console.command');

    $services->set(TriggerReferenceGeneratorCommand::class)
        ->args([
            service(BusinessEventCollector::class),
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(HooksReferenceGenerator::class)
        ->args([
            service('service_container'),
            service('twig'),
            service(ServiceReferenceGenerator::class),
        ])
        ->tag('shopware.scripts_reference.generator');

    $services->set(ServiceReferenceGenerator::class)
        ->args([
            service('twig'),
            param('kernel.project_dir'),
        ])
        ->tag('shopware.scripts_reference.generator');

    $services->set(OpenApiValidationCommand::class)
        ->args([
            service(HttpClientInterface::class),
            service(DefinitionService::class),
        ])
        ->tag('console.command');
};
