<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\ScriptServiceContextPropertyRule;

use Shopware\Core\Framework\Context;

class ServiceWithContext
{
    public function __construct(private readonly Context $context)
    {
    }
}
