<?php declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Replaces the real translation repository client, so tests never reach the network.
    // Must live in this bundle: snippet.xml is loaded after the Framework bundle's
    // services_test.xml and would silently win otherwise (see issue #18067).
    $services->set('shopware.translation.mock_handler', MockHandler::class)
        ->public();

    $services->set('shopware.translation.client', Client::class)
        ->args([
            [
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([service('shopware.translation.mock_handler')]),
            ],
        ]);
};
