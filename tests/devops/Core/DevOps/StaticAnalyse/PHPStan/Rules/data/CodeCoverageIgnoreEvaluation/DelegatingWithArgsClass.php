<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class DelegatingWithArgsClass
{
    /**
     * @param object{find: \Closure} $repo
     */
    public function __construct(private readonly object $repo)
    {
    }

    public function fetch(string $id): mixed
    {
        return $this->repo->find($id);
    }
}
