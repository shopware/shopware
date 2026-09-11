<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class LocalReassignClass
{
    /**
     * @return array<string, int>
     */
    public function build(string $name): array
    {
        $data = [];
        $data[$name] = 1;

        return $data;
    }
}
