<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

/**
 * @internal
 */
interface TestReflectionClassInterface
{
    public function getName(): string;

    /**
     * @return list<TestReflectionClassInterface>
     */
    public function getParents(): array;
}
