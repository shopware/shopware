<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Framework\Test\TestCaseHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

/**
 * @internal
 */
#[CoversClass(ReflectionHelper::class)]
class ReflectionHelperTest extends TestCase
{
    public function testGetMethodFromProtectedScope(): void
    {
        $class = new FakeClassForHelper();

        $method = ReflectionHelper::getMethod(FakeClassForHelper::class, 'myProtectedMethod');

        static::assertTrue($method->invoke($class));
    }

    public function testGetMethodFromPrivateScope(): void
    {
        $class = new FakeClassForHelper();

        $method = ReflectionHelper::getMethod(FakeClassForHelper::class, 'myPrivateMethod');

        static::assertEquals(['one', 'none'], $method->invoke($class));
    }

    public function testGetPropertyValueFromPrivateScope(): void
    {
        $class = new FakeClassForHelper();

        $propertyValue = ReflectionHelper::getPropertyValue($class, 'privateProperty');

        static::assertSame([1, 2, 3, 4], $propertyValue);
    }

    public function testGetPropertyValueFromProtectedScope(): void
    {
        $class = new FakeClassForHelper();

        $propertyValue = ReflectionHelper::getPropertyValue($class, 'protectedProperty');

        static::assertSame('this is it', $propertyValue);
    }

    public function testGetPropertyFromPrivateScope(): void
    {
        $class = new FakeClassForHelper();
        $expectedValue = [5, 6, 7];

        $property = ReflectionHelper::getProperty(FakeClassForHelper::class, 'privateProperty');
        $property->setValue($class, $expectedValue);

        static::assertSame($expectedValue, $property->getValue($class));
    }

    public function testGetPropertyFromProtectedScope(): void
    {
        $class = new FakeClassForHelper();
        $expectedValue = 'override with this';

        $property = ReflectionHelper::getProperty(FakeClassForHelper::class, 'protectedProperty');
        $property->setValue($class, $expectedValue);

        static::assertSame($expectedValue, $property->getValue($class));
    }

    public function testGetFileNameReturnsClassFileName(): void
    {
        $fileName = ReflectionHelper::getFileName(FakeClassForHelper::class);

        static::assertSame(__DIR__ . '/ReflectionHelperTest.php', $fileName);
    }

    public function testGetFileNameReturnsFalseWithAClassFromPHPCore(): void
    {
        $fileName = ReflectionHelper::getFileName(\stdClass::class);

        static::assertFalse($fileName);
    }
}
/**
 * @internal
 */
final class FakeClassForHelper
{
    protected string $protectedProperty = 'this is it';

    /**
     * @var array|int[]
     */
    private array $privateProperty = [1, 2, 3, 4];

    public function __construct()
    {
    }

    public function myAddElement(int $item): void
    {
        if (!\in_array($item, $this->privateProperty, true)) {
            $this->privateProperty[] = $item;
        }
    }

    protected function myProtectedMethod(): bool
    {
        return true;
    }

    /**
     * @return string[]
     */
    protected function myPrivateMethod(): array
    {
        return ['one', 'none'];
    }
}
