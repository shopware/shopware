<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\NodeVisitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\DeprecatedInputExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\DeprecatedInputNodeVisitor;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Feature\Triggerer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(DeprecatedInputNodeVisitor::class)]
class DeprecatedInputNodeVisitorTest extends TestCase
{
    use EnvTestBehaviour;

    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $featureConfigBackup;

    private ?Triggerer $triggererBackup;

    protected function setUp(): void
    {
        $this->featureConfigBackup = Feature::getRegisteredFeatures();
        $this->triggererBackup = Feature::$triggerer;

        Feature::resetRegisteredFeatures();
        Feature::registerFeature('v6.8.0.0', ['major' => true, 'default' => false]);
        Feature::$emitDeprecations = true;

        $this->setEnvVars([
            'V6_8_0_0' => false,
            'FEATURE_ALL' => false,
            'TESTS_RUNNING' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
        Feature::$triggerer = $this->triggererBackup;
        Feature::$emitDeprecations = true;
    }

    public function testReportsOnlyWhenNullCoalescingReadsTheDeprecatedRootInput(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->once())
            ->method('deprecation')
            ->with('', '', static::callback(static fn (string $message): bool => \str_contains($message, 'Twig input "type"')
                && \str_contains($message, 'Use "addressType" instead.')
                && \str_contains($message, 'feature "v6.8.0.0"')));
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% sw_deprecated input 'type' replaced_by='addressType' removed_in='v6.8.0.0' %}
{% set addressType = addressType ?? type %}{{ addressType }}
TWIG,
        ]);

        static::assertSame('shipping', $twig->render('index.html.twig', ['type' => 'shipping']));
    }

    public function testDoesNotReportWhenNullCoalescingUsesTheReplacement(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% sw_deprecated input 'type' replaced_by='addressType' removed_in='v6.8.0.0' %}
{% set addressType = addressType ?? type %}{{ addressType }}
TWIG,
        ]);

        static::assertSame('billing', $twig->render('index.html.twig', ['addressType' => 'billing', 'type' => 'shipping']));
    }

    public function testDefinedProbeDoesNotReportButFollowingReadDoes(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->once())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% sw_deprecated input 'type' message='Stop passing the legacy input.' removed_in='v6.8.0.0' %}
{{ type is defined ? type : 'missing' }}
TWIG,
        ]);

        static::assertSame('missing', $twig->render('index.html.twig'));
        static::assertSame('shipping', $twig->render('index.html.twig', ['type' => 'shipping']));
    }

    public function testChildTemplateInheritsNestedInputDeclaration(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->once())
            ->method('deprecation')
            ->with('', '', static::callback(static fn (string $message): bool => \str_contains($message, 'Twig input "child.snippet_name" declared in "base.html.twig"')
                && \str_contains($message, 'Accessed in "@Storefront/child.html.twig"')));
        Feature::$triggerer = $triggerer;

        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder->expects($this->once())
            ->method('find')
            ->with('base.html.twig', false, '@Storefront/child.html.twig')
            ->willReturn('base.html.twig');

        $twig = new Environment(new ArrayLoader([
            'base.html.twig' => <<<'TWIG'
{% sw_deprecated input 'child.snippet_name' replaced_by='child.name' removed_in='v6.8.0.0' %}
{% block content %}{{ child.name }}{% endblock %}
TWIG,
            '@Storefront/child.html.twig' => <<<'TWIG'
{% sw_extends 'base.html.twig' %}
{% block content %}{{ child.snippet_name }}{% endblock %}
TWIG,
        ]), ['strict_variables' => true]);
        $twig->addExtension(new NodeExtension($templateFinder, static::createStub(TemplateScopeDetector::class)));
        $twig->addExtension(new DeprecatedInputExtension());

        static::assertSame('legacy', $twig->render('@Storefront/child.html.twig', [
            'child' => ['name' => 'current', 'snippet_name' => 'legacy'],
        ]));
    }

    public function testActiveRemovalFlagThrowsBeforeReadingTheInput(): void
    {
        $this->setEnvVars(['V6_8_0_0' => true]);
        $input = new CountingArrayAccess(['snippet_name' => 'legacy']);

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% sw_deprecated input 'child.snippet_name' replaced_by='child.name' removed_in='v6.8.0.0' %}
{{ child.snippet_name }}
TWIG,
        ]);

        try {
            $twig->render('index.html.twig', ['child' => $input]);
            static::fail('The active removal flag should reject the deprecated input read.');
        } catch (RuntimeError $exception) {
            static::assertStringContainsString('Tried to access deprecated functionality', $exception->getMessage());
            static::assertInstanceOf(FeatureException::class, $exception->getPrevious());
        }

        static::assertSame(0, $input->reads);
    }

    public function testDeclarationKeepsBlockOnlyTemplateTraitable(): void
    {
        $twig = $this->createTwig([
            'trait.html.twig' => <<<'TWIG'
{% sw_deprecated input 'type' replaced_by='addressType' removed_in='v6.8.0.0' %}
{% block content %}content{% endblock %}
TWIG,
            'index.html.twig' => <<<'TWIG'
{% use 'trait.html.twig' %}
{{ block('content') }}
TWIG,
        ]);

        static::assertSame('content', $twig->render('index.html.twig'));
    }

    public function testDoesNotReportReadsOfLocalVariablesWithTheSamePath(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% sw_deprecated input 'type' replaced_by='addressType' removed_in='v6.8.0.0' %}
{% sw_deprecated input 'child.snippet_name' replaced_by='child.name' removed_in='v6.8.0.0' %}
{% set type = 'local' %}
{{ type }}
{% for child in children %}{{ child.snippet_name }}{% endfor %}
{% with { type: 'scoped' } %}{{ type }}{% endwith %}
TWIG,
        ]);

        static::assertSame("local\nlocalscoped", $twig->render('index.html.twig', [
            'children' => [['snippet_name' => 'local']],
        ]));
    }

    public function testRejectsDeclarationInsideBlock(): void
    {
        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% block content %}
    {% sw_deprecated input 'type' replaced_by='addressType' removed_in='v6.8.0.0' %}
{% endblock %}
TWIG,
        ]);

        try {
            $twig->load('index.html.twig');
            static::fail('A nested input declaration should not compile.');
        } catch (SyntaxError $exception) {
            static::assertStringContainsString('must be declared at the template root', $exception->getMessage());
        }
    }

    public function testRejectsDuplicateInheritedDeclaration(): void
    {
        $twig = $this->createTwig([
            'base.html.twig' => '{% sw_deprecated input \'type\' replaced_by=\'addressType\' removed_in=\'v6.8.0.0\' %}',
            'child.html.twig' => <<<'TWIG'
{% extends 'base.html.twig' %}
{% sw_deprecated input 'type' replaced_by='addressType' removed_in='v6.8.0.0' %}
TWIG,
        ]);

        try {
            $twig->load('child.html.twig');
            static::fail('A duplicate inherited declaration should not compile.');
        } catch (SyntaxError $exception) {
            static::assertStringContainsString('has more than one deprecation declaration', $exception->getMessage());
        }
    }

    /**
     * @param array<string, string> $templates
     */
    private function createTwig(array $templates): Environment
    {
        $twig = new Environment(new ArrayLoader($templates), ['strict_variables' => true]);
        $twig->addExtension(new DeprecatedInputExtension());

        return $twig;
    }
}

/**
 * @internal
 *
 * @implements \ArrayAccess<string, mixed>
 */
final class CountingArrayAccess implements \ArrayAccess
{
    public int $reads = 0;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private readonly array $values)
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return \is_string($offset) && \array_key_exists($offset, $this->values);
    }

    public function offsetGet(mixed $offset): mixed
    {
        ++$this->reads;

        return \is_string($offset) ? $this->values[$offset] : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('The test input is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('The test input is read-only.');
    }
}
