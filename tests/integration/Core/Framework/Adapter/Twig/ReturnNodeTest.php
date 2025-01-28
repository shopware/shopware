<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig;

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

        $renderer = static::getContainer()->get(StringTemplateRenderer::class);

        $result = $renderer->render($content, $data, Context::createDefaultContext());

        static::assertEquals($expected, trim($result), 'Failure by rendering template: ' . $template);
    }

    public static function nodeProvider(): \Generator
    {
        yield 'Test call' => ['sw-function/call-case.html.twig', '1'];
        yield 'Test assign' => ['sw-function/assign-case.html.twig', '1'];
        yield 'Test if case' => ['sw-function/if-case.html.twig', '1', ['x' => 1]];
        yield 'Test else case' => ['sw-function/if-case.html.twig', '2', ['x' => 2]];
        yield 'Test array case' => ['sw-function/array-case.html.twig', '2'];
        yield 'Test call (deprecated macro)' => ['call-case.html.twig', '1'];
        yield 'Test assign (deprecated macro)' => ['assign-case.html.twig', '1'];
        yield 'Test if case (deprecated macro)' => ['if-case.html.twig', '1', ['x' => 1]];
        yield 'Test else case (deprecated macro)' => ['if-case.html.twig', '2', ['x' => 2]];
        yield 'Test array case (deprecated macro)' => ['array-case.html.twig', '2'];
        yield 'Test call (new syntax)' => ['new-syntax/call-case.html.twig', '1'];
        yield 'Test assign (new syntax)' => ['new-syntax/assign-case.html.twig', '1'];
        yield 'Test if case (new syntax)' => ['new-syntax/if-case.html.twig', '1', ['x' => 1]];
        yield 'Test else case (new syntax)' => ['new-syntax/if-case.html.twig', '2', ['x' => 2]];
        yield 'Test array case (new syntax)' => ['new-syntax/array-case.html.twig', '2', ['x' => 2]];
    }
}
