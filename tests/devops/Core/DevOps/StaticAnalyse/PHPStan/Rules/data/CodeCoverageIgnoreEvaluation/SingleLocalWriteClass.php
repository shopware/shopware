<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class SingleLocalWriteClass
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function passThrough(array $data): array
    {
        $copy = $data;

        return $copy;
    }

    public function fromPair(): string
    {
        [$first, $second] = ['a', 'b'];

        return $first . $second;
    }
}
