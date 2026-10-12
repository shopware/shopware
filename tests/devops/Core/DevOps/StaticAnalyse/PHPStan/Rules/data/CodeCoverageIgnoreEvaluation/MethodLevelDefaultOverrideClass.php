<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

class MethodLevelDefaultOverrideClass extends DefaultingConstructorParent
{
    /**
     * @codeCoverageIgnore
     */
    public function __construct(string $name, int $maxLength = 64)
    {
        parent::__construct($name, $maxLength);
    }
}
