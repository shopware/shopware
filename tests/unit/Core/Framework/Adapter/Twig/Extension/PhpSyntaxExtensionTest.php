<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Util\Hasher;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(PhpSyntaxExtension::class)]
class PhpSyntaxExtensionTest extends TestCase
{
    public function testEmptyOperators(): void
    {
        $extension = new PhpSyntaxExtension();

        // Since Twig 3.21 using operators is deprecated, but still supported
        static::assertSame([[], []], $extension->getOperators());

        // The operators are replaced by expression parsers
        static::assertCount(4, $extension->getExpressionParsers());
        static::assertSame('||', $extension->getExpressionParsers()[0]->getName());
        static::assertSame('&&', $extension->getExpressionParsers()[1]->getName());
        static::assertSame('===', $extension->getExpressionParsers()[2]->getName());
        static::assertSame('!==', $extension->getExpressionParsers()[3]->getName());
    }

    public function testSyntax(): void
    {
        $template = file_get_contents(__DIR__ . '/fixture/php-syntax-extension.html.twig');
        static::assertIsString($template);

        $environment = new Environment(new ArrayLoader());
        $environment->addExtension(new PhpSyntaxExtension());
        $renderer = new StringTemplateRenderer($environment, sys_get_temp_dir());

        $jsonEncodeData = [
            -4,
            'foo' => 'bar',
            'Shopware/Code',
            'list' => [
                ['foo', 'bar'],
            ],
        ];

        $data = [
            'test' => 'test',
            'list' => [-4, 'foo', 'bar'],
            'trueValue' => true,
            'falseValue' => false,
            'stringValue' => 'string',
            'scalarValue' => 1,
            'objectValue' => new ArrayStruct(),
            'intValue' => 1,
            'floatValue' => 1.1,
            'callableValue' => function (): void {
            },
            'arrayValue' => [],
            'jsonEncode' => [
                'data' => $jsonEncodeData,
                'expected' => [
                    json_encode($jsonEncodeData),
                    json_encode($jsonEncodeData, \JSON_UNESCAPED_SLASHES),
                    json_encode($jsonEncodeData, \JSON_PRETTY_PRINT),
                    json_encode($jsonEncodeData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];

        $result = $renderer->render($template, $data, Context::createDefaultContext());

        $expected = '';
        for ($i = 1; $i <= 22; ++$i) {
            $expected .= '-' . $i;
        }
        foreach ($data['jsonEncode']['expected'] as $index => $any) {
            $expected .= '-jsonEncode' . $index;
        }

        static::assertSame($expected, $result, 'Failure in php syntax support in twig rendering');
    }

    #[DataProvider('sha256FilterProvider')]
    public function testSha256Filter(mixed $input, string $expected): void
    {
        $environment = new Environment(new ArrayLoader([
            'test_template' => '{{ value|sha256 }}',
        ]));
        $environment->addExtension(new PhpSyntaxExtension());

        $result = $environment->render('test_template', ['value' => $input]);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{input: mixed, expected: string}>
     */
    public static function sha256FilterProvider(): iterable
    {
        yield 'string input' => [
            'input' => 'test@example.com',
            'expected' => Hasher::hash('test@example.com', 'sha256'),
        ];

        yield 'array input gets json encoded' => [
            'input' => ['foo' => 'bar', 'baz' => 123],
            'expected' => Hasher::hash(json_encode(['foo' => 'bar', 'baz' => 123], \JSON_THROW_ON_ERROR), 'sha256'),
        ];

        yield 'simple array' => [
            'input' => ['a', 'b', 'c'],
            'expected' => Hasher::hash(json_encode(['a', 'b', 'c'], \JSON_THROW_ON_ERROR), 'sha256'),
        ];

        yield 'nested array' => [
            'input' => ['nested' => ['key' => 'value']],
            'expected' => Hasher::hash(json_encode(['nested' => ['key' => 'value']], \JSON_THROW_ON_ERROR), 'sha256'),
        ];

        yield 'empty string' => [
            'input' => '',
            'expected' => Hasher::hash('', 'sha256'),
        ];

        yield 'numeric string' => [
            'input' => '12345',
            'expected' => Hasher::hash('12345', 'sha256'),
        ];
    }

    public function testSha256FilterThrowsExceptionForInvalidType(): void
    {
        $environment = new Environment(new ArrayLoader([
            'test' => '{{ value|sha256 }}',
        ]));
        $environment->addExtension(new PhpSyntaxExtension());

        $invalidObject = new \stdClass();

        $this->expectExceptionObject(
            AdapterException::invalidArgument(
                \sprintf('The sha256 filter expects a string or array as input, %s given', $invalidObject::class)
            )
        );

        try {
            $environment->render('test', ['value' => $invalidObject]);
        } catch (RuntimeError $e) {
            $previous = $e->getPrevious();
            static::assertNotNull($previous);

            throw $previous;
        }
    }
}
