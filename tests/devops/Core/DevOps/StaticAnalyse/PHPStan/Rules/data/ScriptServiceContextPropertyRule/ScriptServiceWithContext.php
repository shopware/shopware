<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\ScriptServiceContextPropertyRule;

use Shopware\Core\Framework\Context;

/**
 * @script-service data_loading
 */
class ScriptServiceWithContext
{
    private ?Context $lastContext = null;

    public function __construct(
        private readonly Context $context,
        private readonly string $entityName,
    ) {
    }
}
