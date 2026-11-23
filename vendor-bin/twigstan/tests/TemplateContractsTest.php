<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantStringType;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateContracts::class)]
#[CoversClass(TemplateContractContextExpression::class)]
class TemplateContractsTest extends PHPStanTestCase
{
    private TypeStringResolver $types;

    protected function setUp(): void
    {
        parent::setUp();
        $this->types = self::getContainer()->getByType(TypeStringResolver::class);
    }

    public function testOnlyOptsInForTheDocumentationMarkerOnTypes(): void
    {
        $twig = new Environment(new ArrayLoader([
            'contract.twig' => "{## template-contract ##}\n{% types {name: 'string', size?: 'string|null'} %}",
            'unmarked.twig' => '{% types {name: \'string\'} %}',
            'ordinary.twig' => '{# template-contract #}{% types {name: \'string\'} %}',
            'verbatim.twig' => '{% verbatim %}{## template-contract ##}{% types {name: \'string\'} %}{% endverbatim %}',
        ]));
        $contracts = new TemplateContracts($twig, $this->types);

        $type = $contracts->project('contract.twig');
        static::assertNotNull($type);
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('name'))->yes());
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('size'))->maybe());
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('unrelated'))->no());
        foreach (['unmarked.twig', 'ordinary.twig', 'verbatim.twig', 'missing.twig'] as $template) {
            static::assertNull($contracts->project($template));
        }
    }

    public function testRejectsAnInvalidCallerAmongManyValidCallers(): void
    {
        $contracts = new TemplateContracts(new Environment(new ArrayLoader([
            'contract.twig' => '{## template-contract ##}{% types {name: \'string\', size?: \'string|null\'} %}',
        ])), $this->types);
        for ($i = 0; $i < 147; ++$i) {
            static::assertSame([], $contracts->validate('contract.twig', [$this->types->resolve('array{name: \'icon-' . $i . '\', extra: int}')]));
        }

        $errors = $contracts->validate('contract.twig', [$this->types->resolve('array{name: int}')]);
        static::assertCount(1, $errors);
        static::assertSame('Template "contract.twig" expects "name" to be string, int given.', $errors[0]->getMessage());
        static::assertSame([], $contracts->validate('contract.twig', [$this->types->resolve('array{name: mixed, size: null}')]));
        static::assertCount(1, $contracts->validate('contract.twig', [$this->types->resolve('array{name: int|string}')]));
        static::assertCount(1, $contracts->validate('contract.twig', [$this->types->resolve('array{name?: string}')]));
        static::assertCount(1, $contracts->validate('contract.twig', [$this->types->resolve('array{}')]));
    }

    public function testTracksContractAndUnmarkedTemplateDependenciesOnCachedLookups(): void
    {
        $loader = static::createMock(LoaderInterface::class);
        $loader->expects($this->exactly(2))->method('getSourceContext')->willReturnCallback(static fn (string $name) => new Source(
            $name === 'contract.twig' ? '{## template-contract ##}{% types {name: \'string\'} %}' : '{{ name }}',
            $name,
            '/templates/' . $name,
        ));
        $contracts = new TemplateContracts(new Environment($loader), $this->types);
        $dependencies = static::createMock(DependencyTracker::class);
        $tracked = [];
        $dependencies->expects($this->exactly(4))->method('trackFileDependency')->willReturnCallback(static function (string $path) use (&$tracked): void {
            $tracked[] = $path;
        });

        foreach (['contract.twig', 'unmarked.twig', 'contract.twig', 'unmarked.twig'] as $template) {
            $contracts->project($template, $dependencies);
        }

        static::assertSame(['/templates/contract.twig', '/templates/unmarked.twig', '/templates/contract.twig', '/templates/unmarked.twig'], $tracked);
    }

    public function testPreservesOverridePriorityAndDoesNotHideKnownErrorsBehindMixed(): void
    {
        $contracts = new TemplateContracts(new Environment(new ArrayLoader([
            'contract.twig' => '{## template-contract ##}{% types {name: \'string\', size?: \'string|null\'} %}',
        ])), $this->types);

        static::assertSame([], $contracts->validate('contract.twig', [
            $this->types->resolve('array{name: string, size: null}'),
            $this->types->resolve('array{name: int, size: int}'),
        ]));
        $errors = $contracts->validate('contract.twig', [
            $this->types->resolve('array{name: string, size?: int}'),
            $this->types->resolve('array{size: mixed}'),
        ]);
        static::assertCount(1, $errors);
        static::assertSame('Template "contract.twig" expects "size" to be string|null, int given.', $errors[0]->getMessage());
    }

    public function testProjectsOnlyTheDeclaredFieldsBeforeCallerAggregation(): void
    {
        $contracts = new TemplateContracts(new Environment(new ArrayLoader([
            'contract.twig' => '{## template-contract ##}{% types {name: \'string\', size?: \'string|null\'} %}',
        ])), $this->types);
        $call = new Node\Expr\StaticCall(new Node\Name\FullyQualified(TemplateContracts::class), 'context', [
            new Node\Arg(new Node\Expr\Variable('env')), new Node\Arg(new Node\Expr\Variable('context')),
            new Node\Arg(new Node\Expr\Variable('variables')), new Node\Arg(new Node\Expr\Variable('withContext')),
            new Node\Arg(new Node\Scalar\String_('contract.twig')),
            new Node\Arg(new Node\Scalar\String_('array{name: string, size?: string|null}')),
        ]);
        $scope = static::createStubForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        $scope->method('getType')->willReturnCallback(static fn (Node\Scalar\String_ $expr) => new ConstantStringType($expr->value));
        $result = $contracts->getTypeFromStaticMethodCall(static::createStub(MethodReflection::class), $call, $scope);

        static::assertNotNull($result);
        static::assertSame('array{name: string, size?: string|null}', (new Printer())->print($result->toPhpDocNode()));
    }

    public function testMarkerHasNoEffectOnRuntimeContext(): void
    {
        $twig = new Environment(new ArrayLoader([
            'contract.twig' => "{# package #}\n\n{%- block icon -%}\n    {## template-contract ##}\n    {%- types {name: 'string'} -%}\n    {{ name }}:{{ extra }}{%- endblock -%}",
        ]), ['strict_variables' => true]);

        static::assertSame('icon:still available', $twig->render('contract.twig', ['name' => 'icon', 'extra' => 'still available']));
    }

    public function testCompilesContractsIntoCallerCodeWithoutChangingIncludeSemantics(): void
    {
        $loader = new ArrayLoader([
            'caller.twig' => '{% include \'contract.twig\' with {name: \'override\'} %}|{% include \'contract.twig\' with {name: \'only\'} only %}|{% include \'unmarked.twig\' %}',
            'contract.twig' => '{## template-contract ##}{% types {name: \'string\', size?: \'string|null\'} %}{{ name }}:{{ extra ?? \'absent\' }}',
            'unmarked.twig' => '{{ extra }}',
        ]);
        $ordinary = new Environment($loader, ['strict_variables' => true]);
        $analysis = new Environment($loader, ['strict_variables' => true, 'debug' => true]);
        $analysis->addNodeVisitor(new StorefrontIncludeNodeVisitor(__DIR__));
        $code = $analysis->compileSource($loader->getSourceContext('caller.twig'));
        static::assertSame(2, substr_count($code, TemplateContracts::class . '::context('));
        static::assertStringContainsString('array{name: string, size?: string|null}', $code);
        $context = ['name' => 'parent', 'extra' => 'inherited'];
        static::assertSame('override:inherited|only:absent|inherited', $ordinary->render('caller.twig', $context));
        static::assertSame($ordinary->render('caller.twig', $context), $analysis->render('caller.twig', $context));

        $changed = new ArrayLoader(['contract.twig' => '{## template-contract ##}{% types {name: \'int\'} %}']);
        $changed->setTemplate('caller.twig', '{% include \'contract.twig\' %}');
        $twig = new Environment($changed);
        $twig->addNodeVisitor(new StorefrontIncludeNodeVisitor(__DIR__));
        static::assertStringContainsString('array{name: int}', $twig->compileSource($changed->getSourceContext('caller.twig')));
    }

    public function testRejectsAMarkerOnAnUnrelatedTag(): void
    {
        $contracts = new TemplateContracts(new Environment(new ArrayLoader([
            'invalid.twig' => '{## template-contract ##}{% set value = 1 %}',
        ])), $this->types);

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The template-contract marker must precede a single types declaration');
        $contracts->project('invalid.twig');
    }
}
