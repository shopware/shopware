<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\PasswordHasher\NativePasswordHasher;
use Shopware\Core\Framework\PasswordHasher\PasswordHasherRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('shopware.password_hasher.customer', NativePasswordHasher::class)
        ->args([
            param('shopware.password_hasher.customer.algorithm'),
            param('shopware.password_hasher.customer.options'),
        ]);

    $services->set('shopware.password_hasher.admin', NativePasswordHasher::class)
        ->args([
            param('shopware.password_hasher.admin.algorithm'),
            param('shopware.password_hasher.admin.options'),
        ]);

    $services->set(PasswordHasherRegistry::class)
        ->args([
            [
                PasswordField::FOR_CUSTOMER => service('shopware.password_hasher.customer'),
                PasswordField::FOR_ADMIN => service('shopware.password_hasher.admin'),
            ],
        ]);
};
