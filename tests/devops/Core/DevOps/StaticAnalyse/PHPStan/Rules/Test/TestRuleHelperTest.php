<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Test;

use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;

/**
 * @internal
 */
#[CoversClass(TestRuleHelper::class)]
class TestRuleHelperTest extends TestCase
{
    #[DataProvider('classProvider')]
    public function testIsTestClass(string $className, bool $extendsTestCase, bool $isTestClass, bool $isUnitTestClass): void
    {
        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection
            ->method('getName')
            ->willReturn($className);

        if ($extendsTestCase) {
            $parentClass = $this->createMock(ClassReflection::class);
            $parentClass
                ->method('getName')
                ->willReturn(TestCase::class);

            $classReflection
                ->method('getParentClass')
                ->willReturn($parentClass);
        }

        static::assertEquals($isTestClass, TestRuleHelper::isTestClass($classReflection));
        static::assertEquals($isUnitTestClass, TestRuleHelper::isUnitTestClass($classReflection));
    }

    public static function classProvider(): \Generator
    {
        yield [
            'className' => 'Shopware\Some\NonTestClass',
            'extendsTestCase' => false,
            'isTestClass' => false,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopware\Commercial\Tests\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopware\Tests\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopware\Tests\Unit\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => true,
        ];

        yield [
            'className' => 'Shopware\Tests\Integration\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopware\Tests\SomeNonTestClass',
            'extendsTestCase' => false,
            'isTestClass' => false,
            'isUnitTestClass' => false,
        ];
    }
}
