<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

class ParentChainConstructorParent
{
    public function __construct(public readonly string $base)
    {
    }
}
