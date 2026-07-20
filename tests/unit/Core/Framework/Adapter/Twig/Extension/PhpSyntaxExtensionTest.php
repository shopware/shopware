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
            'callableValue' => static function (): void {
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

    #[DataProvider('hashFilterProvider')]
    public function testHashFilter(string $algorithm, mixed $input, string $expected): void
    {
        $environment = new Environment(new ArrayLoader([
            'test_template' => '{{ value|' . $algorithm . ' }}',
        ]));
        $environment->addExtension(new PhpSyntaxExtension());

        $result = $environment->render('test_template', ['value' => $input]);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{algorithm: string, input: mixed, expected: string}>
     */
    public static function hashFilterProvider(): iterable
    {
        foreach (['md5', 'sha256'] as $algo) {
            yield $algo . ' string input' => [
                'algorithm' => $algo,
                'input' => 'test@example.com',
                'expected' => Hasher::hash('test@example.com', $algo),
            ];

            yield $algo . ' array input gets json encoded' => [
                'algorithm' => $algo,
                'input' => ['foo' => 'bar', 'baz' => 123],
                'expected' => Hasher::hash(json_encode(['foo' => 'bar', 'baz' => 123], \JSON_THROW_ON_ERROR), $algo),
            ];

            yield $algo . ' nested array' => [
                'algorithm' => $algo,
                'input' => ['nested' => ['key' => 'value']],
                'expected' => Hasher::hash(json_encode(['nested' => ['key' => 'value']], \JSON_THROW_ON_ERROR), $algo),
            ];

            yield $algo . ' empty string' => [
                'algorithm' => $algo,
                'input' => '',
                'expected' => Hasher::hash('', $algo),
            ];
        }
    }

    #[DataProvider('hashFilterInvalidTypeProvider')]
    public function testHashFilterThrowsExceptionForNonEncodableArray(string $algorithm): void
    {
        $environment = new Environment(new ArrayLoader([
            'test' => '{{ value|' . $algorithm . ' }}',
        ]));
        $environment->addExtension(new PhpSyntaxExtension());

        $this->expectExceptionObject(AdapterException::invalidArgument(\sprintf('The %s filter failed to encode array input: %s', $algorithm, 'Inf and NaN cannot be JSON encoded')));

        try {
            $environment->render('test', ['value' => [\NAN]]);
        } catch (RuntimeError $e) {
            $previous = $e->getPrevious();
            static::assertNotNull($previous);

            throw $previous;
        }
    }

    #[DataProvider('hashFilterInvalidTypeProvider')]
    public function testHashFilterThrowsExceptionForInvalidType(string $algorithm): void
    {
        $environment = new Environment(new ArrayLoader([
            'test' => '{{ value|' . $algorithm . ' }}',
        ]));
        $environment->addExtension(new PhpSyntaxExtension());

        $this->expectExceptionObject(
            AdapterException::invalidArgument(
                \sprintf('The %s filter expects a string or array as input, stdClass given', $algorithm)
            )
        );

        try {
            $environment->render('test', ['value' => new \stdClass()]);
        } catch (RuntimeError $e) {
            $previous = $e->getPrevious();
            static::assertNotNull($previous);

            throw $previous;
        }
    }

    /**
     * @return iterable<string, array{algorithm: string}>
     */
    public static function hashFilterInvalidTypeProvider(): iterable
    {
        yield 'md5' => ['algorithm' => 'md5'];
        yield 'sha256' => ['algorithm' => 'sha256'];
    }
}
