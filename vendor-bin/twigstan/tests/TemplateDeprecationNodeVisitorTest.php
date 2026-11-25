<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Output\NullOutput;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Source;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;
use TwigStan\Processing\Compilation\Parser\TwigNodeParser;
use TwigStan\Twig\Metadata\MetadataAnalyzer;
use TwigStan\Twig\Node\NodeFinder;
use TwigStan\Twig\TwigFileCanonicalizer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateDeprecationNodeVisitor::class)]
class TemplateDeprecationNodeVisitorTest extends TestCase
{
    public function testEmbeddedModuleDiagnosticsAreNotDuplicatedByTheEnclosingModule(): void
    {
        $templates = [
            'base.twig' => '{## @deprecated tag:v6.8.0 - Removed. ##}{% block old %}Old{% endblock %}',
            'caller.twig' => "{% embed 'base.twig' %}\n{% block old %}New{% endblock %}{% endembed %}",
        ];
        $twig = new Environment(new ArrayLoader($templates));
        $twig->addNodeVisitor(new TemplateDeprecationNodeVisitor(array_keys($templates)));
        $messages = [];
        set_error_handler(static function (int $level, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $twig->parse($twig->tokenize(new Source($templates['caller.twig'], 'caller.twig')));
        } finally {
            restore_error_handler();
        }

        static::assertCount(1, $messages);
        static::assertStringContainsString('Block override "old" is deprecated:', $messages[0]);
        static::assertStringContainsString('in "caller.twig" at line 2.', $messages[0]);
    }

    public function testOnlyAddsAnalysisDiagnosticsWithoutChangingCompiledRuntimeCode(): void
    {
        $source = new Source('{## @deprecated tag:v6.8.0 - Removed. ##}{% set old = \'Old\' %}{{ old }}', 'view.twig');
        $loader = new ArrayLoader(['view.twig' => $source->getCode()]);
        $ordinary = new Environment($loader);
        $analysis = new Environment($loader);
        $analysis->addNodeVisitor(new TemplateDeprecationNodeVisitor(['view.twig']));
        $messages = [];
        set_error_handler(static function (int $level, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $compiled = $analysis->compileSource($source);
        } finally {
            restore_error_handler();
        }

        static::assertSame($ordinary->compileSource($source), $compiled);
        static::assertCount(1, $messages);
    }

    public function testCompleteAnalysisReportsAllThreeDownstreamDeprecations(): void
    {
        $root = \dirname(__DIR__, 3);
        $container = ContainerFactory::fromFile($root, __DIR__ . '/fixtures/template-deprecations/twigstan.php')->create();

        $result = $container->getByType(AnalyzeCommand::class)->analyze([], new NullOutput(), new NullOutput(), false, false, null);

        static::assertSame([], $result->fileSpecificErrors);
        static::assertCount(3, $result->errors);
        $lines = [];
        foreach ($result->errors as $error) {
            static::assertSame('twig.deprecated', $error->identifier);
            static::assertNotNull($error->twigSourceLocation);
            static::assertStringEndsWith('/child.twig', $error->twigSourceLocation->fileName);
            $lines[] = $error->twigSourceLocation->lineNumber;
        }
        sort($lines);
        static::assertSame([2, 3, 4], $lines);
    }

    public function testTwigStanReportsTheDownstreamUsageLocation(): void
    {
        $twig = new Environment(new FilesystemLoader(__DIR__ . '/fixtures/template-deprecations'));
        $twig->addNodeVisitor(new TemplateDeprecationNodeVisitor(['base.twig', 'child.twig', 'input.twig']));
        $analyzer = new MetadataAnalyzer($twig, new TwigNodeParser($twig, new TwigFileCanonicalizer($twig)), new NodeFinder());

        $metadata = $analyzer->getMetadata('child.twig');

        static::assertSame([2, 3, 4], array_column($metadata->errors, 'line'));
        static::assertSame(['twig.deprecated', 'twig.deprecated', 'twig.deprecated'], array_column($metadata->errors, 'identifier'));
        static::assertStringContainsString('Block override "old_title" is deprecated:', $metadata->errors[0]['message']);
        static::assertStringContainsString('Variable "oldLabel" is deprecated:', $metadata->errors[1]['message']);
        static::assertStringContainsString('Template input "oldInput" is deprecated:', $metadata->errors[2]['message']);
    }
}
