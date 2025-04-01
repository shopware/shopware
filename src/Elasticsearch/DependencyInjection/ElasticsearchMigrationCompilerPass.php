<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\DependencyInjection;

use Shopware\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ElasticsearchMigrationCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function getMigrationNamespacePart(): string
    {
        return 'Elasticsearch';
    }
}
