<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\SystemCheck\Command\SystemCheckCommand;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SystemCheckCommand::class)
        ->args([
            service(SystemChecker::class),
        ])
        ->tag('console.command');

    $services->set(SystemChecker::class)
        ->args([
            tagged_iterator('shopware.system_check'),
        ]);
};
