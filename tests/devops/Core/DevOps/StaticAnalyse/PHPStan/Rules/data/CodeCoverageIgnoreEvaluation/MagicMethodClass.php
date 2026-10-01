<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class MagicMethodClass
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __get(string $name): mixed
    {
        if (!isset($this->data[$name])) {
            throw new \RuntimeException('missing');
        }

        return $this->data[$name];
    }
}
