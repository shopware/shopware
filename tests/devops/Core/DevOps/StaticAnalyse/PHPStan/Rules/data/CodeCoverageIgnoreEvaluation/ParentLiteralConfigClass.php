<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ParentLiteralConfigClass extends ParentChainConstructorParent
{
    public function __construct()
    {
        parent::__construct('fixed-base');
    }
}
