<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ParentChainWithCallArgClass extends ParentChainConstructorParent
{
    private \Throwable $context;

    public function __construct(\Throwable $context)
    {
        parent::__construct($context->getMessage());
        $this->context = $context;
    }
}
