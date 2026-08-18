<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ParentChainConstructorClass extends ParentChainConstructorParent
{
    public function __construct(string $base, public readonly string $extra)
    {
        parent::__construct($base);
    }
}
