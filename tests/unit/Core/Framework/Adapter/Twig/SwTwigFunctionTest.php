<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Twig\Environment;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(SwTwigFunction::class)]
class SwTwigFunctionTest extends TestCase
{
    private Stub&Environment $environment;

    protected function setUp(): void
    {
        $this->environment = static::createStub(Environment::class);
    }

    /**
     * @return \Generator<string, array{object: Struct, attribute: string, expected: string|bool|null, arguments?: array}>
     */
    public static function getAttributeDataProvider(): \Generator
    {
        $object = new StructForTests();
        $object->setNoGetter(99);
        $object->setValue('valueValue');
        $object->setVisible(true);

        yield 'null value' => [
            'object' => new ArrayStruct(['test' => null]),
            'attribute' => 'test',
            'expected' => null,
        ];

        yield 'boolean true' => [
            'object' => new ArrayStruct(['test' => true]),
            'attribute' => 'test',
            'expected' => true,
        ];

        yield 'boolean false' => [
            'object' => new ArrayStruct(['test' => false]),
            'attribute' => 'test',
            'expected' => false,
        ];

        yield 'just property' => [
            'object' => new ArrayStruct(['test' => 'value']),
            'attribute' => 'test',
            'expected' => 'value',
        ];

        yield 'getter method' => [
            'object' => $object,
            'attribute' => 'value',
            'expected' => 'valueValue',
        ];

        yield 'isVisible method' => [
            'object' => $object,
            'attribute' => 'isVisible',
            'expected' => true,
        ];

        yield 'method with arguments' => [
            'object' => $object,
            'attribute' => 'getNonExistentProperty',
            'arguments' => ['arg1', 'arg2'],
            'expected' => 'result',
        ];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('getAttributeDataProvider')]
    public function testGetAttributeWithVariousInputs(Struct $object, string $attribute, string|bool|null $expected, array $arguments = []): void
    {
        $result = SwTwigFunction::getAttribute(
            $this->environment,
            new Source('', 'empty'),
            $object,
            $attribute,
            $arguments
        );

        static::assertSame($expected, $result);
    }

    public function testGetAttributeFallsBackToCoreExtensionWhenMethodThrows(): void
    {
        $source = new Source('', 'test_template');

        $this->expectExceptionObject(new \RuntimeException('Test exception'));

        $struct = new StructForTests();
        $struct->setThrowException(true);

        SwTwigFunction::getAttribute(
            $this->environment,
            $source,
            $struct,
            'nonExistentProperty'
        );
    }
}

/**
 * @internal
 */
class StructForTests extends Struct
{
    private bool $visible;

    private string $value;

    private int $noGetter;

    private bool $throwException = false;

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function setNoGetter(int $noGetter): void
    {
        $this->noGetter = $noGetter;
        if ($this->noGetter > 0) {
            $this->visible = true;
        }
    }

    public function setThrowException(bool $throwException): void
    {
        $this->throwException = $throwException;
    }

    public function getNonExistentProperty(): string
    {
        if ($this->throwException) {
            throw new \RuntimeException('Test exception');
        }

        return 'result';
    }
}
