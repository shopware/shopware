<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class DelegatingGetterClass
{
    /**
     * @param object{getContext: \Closure, getInner: \Closure} $dep
     */
    public function __construct(private readonly object $dep)
    {
    }

    public function getContext(): mixed
    {
        return $this->dep->getContext();
    }

    public function getDeep(): mixed
    {
        return $this->dep->getInner()->getDeep();
    }

    public function getProp(): mixed
    {
        return $this->dep->prop;
    }
}
