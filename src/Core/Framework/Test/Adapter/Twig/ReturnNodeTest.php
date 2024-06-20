<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ReturnNodeTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param array<string, int> $data
     */
    #[DataProvider('nodeProvider')]
    public function testNode(string $template, string $expected, array $data = []): void
    {
        $file = __DIR__ . '/fixtures/return-node/' . $template;

        $content = (string) file_get_contents($file);

        $renderer = $this->getContainer()->get(StringTemplateRenderer::class);

        $result = $renderer->render($content, $data, Context::createDefaultContext());

        static::assertEquals($expected, $result, 'Failure by rendering template: ' . $template);
    }

    public static function nodeProvider(): \Generator
    {
        yield 'Test call' => ['call-case.html.twig', '1'];
        yield 'Test assign' => ['assign-case.html.twig', '1'];
        yield 'Test if case' => ['if-case.html.twig', '1', ['x' => 1]];
        yield 'Test else case' => ['if-case.html.twig', '2', ['x' => 2]];
        yield 'Test array case' => ['array-case.html.twig', '2'];
    }
}
