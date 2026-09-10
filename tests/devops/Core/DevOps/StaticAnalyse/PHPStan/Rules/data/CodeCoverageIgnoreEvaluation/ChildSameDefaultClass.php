<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ChildSameDefaultClass extends DefaultingConstructorParent
{
    public function __construct(string $name, int $maxLength = 255)
    {
        parent::__construct($name, $maxLength);
    }
}
