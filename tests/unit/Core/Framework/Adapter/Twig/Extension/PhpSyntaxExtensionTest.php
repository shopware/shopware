<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Twig\Environment;
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
}
