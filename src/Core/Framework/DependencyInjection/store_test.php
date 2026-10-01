<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('shopware.store.mock_handler', MockHandler::class)
        ->public();

    $services->set('shopware.store_client', Client::class)
        ->public()
        ->args([
            [
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service('shopware.store.mock_handler'),
                    ]),
            ],
        ]);

    $services->set('shopware.frw.mock_handler', MockHandler::class)
        ->public();

    $services->set('shopware.frw_client', Client::class)
        ->public()
        ->args([
            [
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service('shopware.frw.mock_handler'),
                    ]),
            ],
        ]);

    $services->set('shopware.store_download_client', Client::class)
        ->args([
            [
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service('shopware.store.mock_handler'),
                    ]),
            ],
        ]);

    $services->set(InstanceService::class)
        ->args([
            Kernel::SHOPWARE_FALLBACK_VERSION,
            'this-is-a-unique-id',
        ]);
};
