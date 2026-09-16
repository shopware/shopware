<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\Content\Media\File\FileUrlValidatorInterface;
use Shopware\Core\Content\Test\Media\File\FileUrlValidatorStub;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(FileUrlValidatorInterface::class, FileUrlValidatorStub::class);
};
