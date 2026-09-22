<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class UnsetMutationClass
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function strip(array $data): array
    {
        unset($data['extensions']);

        return $data;
    }
}
