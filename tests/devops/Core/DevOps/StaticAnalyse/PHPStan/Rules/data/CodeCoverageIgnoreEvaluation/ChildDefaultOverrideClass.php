<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ChildDefaultOverrideClass extends DefaultingConstructorParent
{
    public function __construct(string $name, int $maxLength = 64)
    {
        parent::__construct($name, $maxLength);
    }
}
