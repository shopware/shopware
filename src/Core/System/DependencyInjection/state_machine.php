<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\Api\StateMachineActionController;
use Shopware\Core\System\StateMachine\Command\WorkflowDumpCommand;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineLocker;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\StateMachineTransitionValidator;
use Shopware\Core\System\StateMachine\StateMachineTranslationDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(StateMachineActionController::class)
        ->public()
        ->args([
            service(StateMachineRegistry::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(StateMachineRegistry::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
            service('state_machine_history.repository'),
            service('event_dispatcher'),
            service(DefinitionInstanceRegistry::class),
            service(StateMachineLocker::class),
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    // @deprecated tag:v6.8.0 - remove the registration together with StateMachineTransitionValidator
    $services->set(StateMachineTransitionValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(StateMachineLocker::class)
        ->args([
            service('lock.factory'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(WorkflowDumpCommand::class)
        ->args([
            service(StateMachineRegistry::class),
        ])
        ->tag('console.command');

    $services->set(StateMachineDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(StateMachineTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(StateMachineStateDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(StateMachineStateTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(StateMachineTransitionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(StateMachineHistoryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(InitialStateIdLoader::class)
        ->args([
            service(Connection::class),
            service('cache.object'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);
};
