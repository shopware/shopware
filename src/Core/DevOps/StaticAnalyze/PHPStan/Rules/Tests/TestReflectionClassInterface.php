<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

/**
 * @internal
 *
 * This interface is used to mock the PHPstan ClassReflection class in the test cases.
 * Their class is final and cannot be mocked directly.
 *
 * @see Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests\TestRuleHelperTest
 */
interface TestReflectionClassInterface
{
    public function getName(): string;

    /**
     * @return list<TestReflectionClassInterface>
     */
    public function getParents(): array;
}
