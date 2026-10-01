<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Http\Discovery\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Inlined from php-http/discovery Symfony Flex recipe:
 * https://github.com/symfony/recipes/blob/main/php-http/discovery/1.18/config/packages/http_discovery.yaml
 *
 * Shopware does not apply Flex recipes, so these PSR-17 service aliases are registered here.
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('http_discovery.psr17_factory', Psr17Factory::class);

    $services->alias(RequestFactoryInterface::class, 'http_discovery.psr17_factory');
    $services->alias(ResponseFactoryInterface::class, 'http_discovery.psr17_factory');
    $services->alias(ServerRequestFactoryInterface::class, 'http_discovery.psr17_factory');
    $services->alias(StreamFactoryInterface::class, 'http_discovery.psr17_factory');
    $services->alias(UploadedFileFactoryInterface::class, 'http_discovery.psr17_factory');
    $services->alias(UriFactoryInterface::class, 'http_discovery.psr17_factory');
};
