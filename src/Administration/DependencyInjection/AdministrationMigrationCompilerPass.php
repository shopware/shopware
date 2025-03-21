<?php declare(strict_types=1);

namespace Shopware\Administration\DependencyInjection;

use Shopware\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AdministrationMigrationCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function getMigrationNamespacePart(): string
    {
        return 'Administration';
    }
}
