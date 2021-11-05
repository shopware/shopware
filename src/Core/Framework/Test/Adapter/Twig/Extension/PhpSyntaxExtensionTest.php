<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class PhpSyntaxExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSyntax(): void
    {
        $template = file_get_contents(__DIR__ . '/fixture/php-syntax-extension.html.twig');

        $renderer = $this->getContainer()->get(StringTemplateRenderer::class);

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
        ];

        $result = $renderer->render($template, $data, Context::createDefaultContext());

        $expected = '';
        for ($i = 1; $i <= 22; ++$i) {
            $expected .= '-' . $i;
        }

        static::assertEquals($expected, $result, 'Failure in php syntax support in twig rendering');
    }
}
