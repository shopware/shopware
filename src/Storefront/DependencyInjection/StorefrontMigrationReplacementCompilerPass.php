<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class StorefrontMigrationReplacementCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__) . '/Migration';
    }

    protected function getMigrationNamespace(): string
    {
        return 'Shopware\Storefront\Migration';
    }
}
