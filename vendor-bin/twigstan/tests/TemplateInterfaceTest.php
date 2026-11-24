<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateContracts::class)]
class TemplateInterfaceTest extends TypeInferenceTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/fixtures/template-contracts.neon'];
    }

    public function testRecognizesTheExternalInterfaceMarker(): void
    {
        $loader = new ArrayLoader(['input.twig' => '{## external-interface ##}{% types {term?: \'string|null\'} %}']);
        $twig = new Environment($loader);

        static::assertSame([
            'fields' => ['term' => ['type' => 'string|null', 'optional' => true]],
            'project' => false,
        ], TemplateContracts::declaration($twig, $loader->getSourceContext('input.twig')));
    }

    public function testPreservesUndeclaredInputsAndIncludeOverridePriority(): void
    {
        $assertions = self::gatherAssertTypes(__DIR__ . '/fixtures/template-interface-types.php');
        static::assertNotEmpty($assertions);
        foreach ($assertions as $assertion) {
            $this->assertFileAsserts(...$assertion);
        }
    }

    public function testCompilesExternalInterfaceWithoutChangingRuntimeIncludes(): void
    {
        $loader = new ArrayLoader([
            'caller.twig' => '{% include \'input.twig\' with {term: \'override\'} %}|{% include \'input.twig\' with {} only %}',
            'input.twig' => '{## external-interface ##}{% types {term?: \'string|null\'} %}{{ term ?? \'missing\' }}:{{ extra ?? \'absent\' }}',
        ]);
        $ordinary = new Environment($loader, ['strict_variables' => true]);
        $analysis = new Environment($loader, ['strict_variables' => true, 'debug' => true]);
        $analysis->addNodeVisitor(new StorefrontIncludeNodeVisitor(__DIR__));
        $code = $analysis->compileSource($loader->getSourceContext('caller.twig'));

        static::assertSame(2, substr_count($code, TemplateContracts::class . '::context('));
        static::assertStringContainsString('array{term?: string|null}', $code);
        static::assertStringContainsString(', false)', $code);
        $context = ['term' => 'parent', 'extra' => 'inherited'];
        static::assertSame('override:inherited|missing:absent', $ordinary->render('caller.twig', $context));
        static::assertSame($ordinary->render('caller.twig', $context), $analysis->render('caller.twig', $context));
    }

    public function testRejectsTheInterfaceMarkerOnAnUnrelatedTag(): void
    {
        $loader = new ArrayLoader(['input.twig' => '{## external-interface ##}{% set value = 1 %}']);
        $twig = new Environment($loader);

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The external-interface marker must precede a single types declaration');
        TemplateContracts::declaration($twig, $loader->getSourceContext('input.twig'));
    }
}
