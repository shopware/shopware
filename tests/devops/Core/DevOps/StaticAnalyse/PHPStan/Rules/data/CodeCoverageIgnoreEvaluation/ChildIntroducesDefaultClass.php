<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ChildIntroducesDefaultClass extends DefaultingConstructorParent
{
    public function __construct(string $name = 'number')
    {
        parent::__construct($name);
    }
}
