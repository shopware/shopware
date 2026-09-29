<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RateLimiter::class);
};
