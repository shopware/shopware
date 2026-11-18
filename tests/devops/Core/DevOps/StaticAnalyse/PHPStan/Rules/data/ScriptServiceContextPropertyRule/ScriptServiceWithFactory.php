<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\ScriptServiceContextPropertyRule;

use Shopware\Core\Framework\Script\AppContextFactory;

/**
 * @script-service data_loading
 */
class ScriptServiceWithFactory
{
    public function __construct(private readonly AppContextFactory $contextFactory)
    {
    }
}
