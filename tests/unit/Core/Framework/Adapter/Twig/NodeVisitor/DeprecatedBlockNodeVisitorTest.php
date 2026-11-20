<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\NodeVisitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\TwigFeaturesWithInheritanceExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\DeprecatedBlockNodeVisitor;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeprecatedBlockNodeVisitor::class)]
class DeprecatedBlockNodeVisitorTest extends TestCase
{
    private const BASE = '{## @deprecated tag:v6.8.0 - Use current instead. ##}{% block old %}OLD{% block current %}CURRENT{% endblock %}{% endblock %}';

    protected function setUp(): void
    {
        Feature::registerFeatures(['v6.8.0.0', 'TWIG_COMPILE_TIME_OPTIMIZATION']);
    }

    #[DisabledFeatures(['v6.8.0.0', 'TWIG_COMPILE_TIME_OPTIMIZATION'])]
    public function testLegacyBlockAndParentRemainAvailable(): void
    {
        $twig = $this->createTwig([
            'base' => self::BASE,
            'extension' => '{% extends "base" %}{% block old %}EXTENSION:{{ parent() }}{% endblock %}',
        ]);

        static::assertSame('EXTENSION:OLDCURRENT', $twig->render('extension'));
        static::assertTrue($twig->load('base')->hasBlock('old'));
        static::assertSame('OLDCURRENT', $twig->load('base')->renderBlock('old'));
    }

    public function testNormalInvocationSkipsExtensionOverrideAndParent(): void
    {
        $twig = $this->createTwig([
            'base' => self::BASE,
            'extension' => '{% extends "base" %}{% block old %}EXTENSION:{{ parent() }}{% endblock %}',
        ]);

        static::assertSame('', $twig->render('extension'));
    }

    public function testSupportedNestedBlockRemainsOverridable(): void
    {
        $twig = $this->createTwig([
            'base' => self::BASE . '{{ block("current") }}',
            'extension' => '{% extends "base" %}{% block current %}REPLACEMENT{% endblock %}',
        ]);

        static::assertSame('REPLACEMENT', $twig->render('extension'));
        static::assertTrue($twig->load('base')->hasBlock('current'));
        static::assertFalse($twig->load('base')->hasBlock('old'));
        static::assertSame(['current'], $twig->load('base')->getBlockNames());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the 6.8 feature flag.
     */
    #[DataProvider('explicitCalls')]
    public function testExplicitCallOfRemovedCoreBlockThrows(string $call): void
    {
        $twig = $this->createTwig(['base' => self::BASE, 'caller' => $call]);

        $this->expectExceptionObject(new RuntimeError('Block "old" on template "base" does not exist'));

        $twig->render('caller', ['name' => 'old']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function explicitCalls(): iterable
    {
        yield 'direct target' => ['{{ block("old", "base") }}'];
        yield 'dynamic name' => ['{{ block(name, "base") }}'];
        yield 'captured output' => ['{% set output = block("old", "base") %}{{ output }}'];
    }

    public function testExtensionCanExplicitlyCallItsOwnBlock(): void
    {
        $twig = $this->createTwig([
            'base' => self::BASE . '{% block entry %}{% endblock %}',
            'extension' => '{% extends "base" %}{% block entry %}{{ block("old") }}{% endblock %}{% block old %}EXTENSION{% endblock %}',
        ]);

        static::assertSame('EXTENSION', $twig->render('extension'));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the 6.8 feature flag.
     */
    public function testExplicitExtensionCallCannotReachRemovedParent(): void
    {
        $twig = $this->createTwig([
            'base' => self::BASE . '{% block entry %}{% endblock %}',
            'extension' => '{% extends "base" %}{% block entry %}{{ block("old") }}{% endblock %}{% block old %}{{ parent() }}{% endblock %}',
        ]);

        $this->expectExceptionObject(new RuntimeError('Block "old" should not call parent()'));

        $twig->render('extension');
    }

    public function testDefinedProbeDoesNotThrow(): void
    {
        $twig = $this->createTwig(['base' => self::BASE . '{{ block("old") is defined ? "yes" : "no" }}']);

        static::assertSame('no', $twig->render('base'));
    }

    public function testTraitImportKeepsOnlySupportedBlocks(): void
    {
        $twig = $this->createTwig([
            'trait' => self::BASE,
            'consumer' => '{% use "trait" %}{{ block("current") }}:{{ block("old") is defined ? "yes" : "no" }}',
        ]);

        static::assertSame('CURRENT:no', $twig->render('consumer'));
        static::assertSame(['current'], $twig->load('consumer')->getBlockNames());
    }

    public function testSameBlockNameInUnrelatedTemplateIsNotRemoved(): void
    {
        $twig = $this->createTwig(['base' => self::BASE, 'other' => '{% block old %}OTHER{% endblock %}']);

        $twig->load('base');

        static::assertSame('OTHER', $twig->render('other'));
    }

    public function testEmbeddedTemplateDoesNotAffectOuterBlockWithSameName(): void
    {
        $twig = $this->createTwig([
            'base' => '{% block entry %}{% endblock %}',
            'outer' => '{% embed "base" %}{## @deprecated tag:v6.8.0 ##}{% block old %}EMBEDDED{% endblock %}{% endembed %}{% block old %}OUTER{% endblock %}',
        ]);

        static::assertSame('OUTER', $twig->render('outer'));
        static::assertTrue($twig->load('outer')->hasBlock('old'));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the 6.8 feature flag.
     */
    public function testShopwareInheritanceCannotReachRemovedParentThroughSwBlock(): void
    {
        $loader = new ArrayLoader([
            '@Storefront/base' => self::BASE . '{% block entry %}{% endblock %}',
            '@Extension/base' => '{% sw_extends "@Storefront/base" %}{% block old %}{{ parent() }}{% endblock %}',
            'caller' => '{{ sw_block("old", "@Storefront/base") }}',
        ]);
        $twig = new Environment($loader);
        $scopes = new TemplateScopeDetector(new RequestStack());
        $hierarchy = new NamespaceHierarchyBuilder([new class implements TemplateNamespaceHierarchyBuilderInterface {
            public function buildNamespaceHierarchy(array $namespaceHierarchy): array
            {
                return ['Extension' => 1, 'Storefront' => -2];
            }
        }]);
        $finder = new TemplateFinder($twig, $loader, '', $hierarchy, $scopes);
        $twig->addExtension(new NodeExtension($finder, $scopes));
        $twig->addExtension(new TwigFeaturesWithInheritanceExtension($finder));
        $twig->addExtension(new FeatureFlagExtension());

        static::assertSame('@Extension/base', $finder->find('@Storefront/base'));
        static::assertSame('', $twig->render('@Extension/base'));

        $this->expectExceptionObject(new RuntimeError('Block "old" should not call parent()'));

        $twig->render('caller');
    }

    public function testOrdinaryCommentsDoNotChangeRendering(): void
    {
        $twig = $this->createTwig(['base' => '{# @deprecated tag:v6.8.0 #}{% block old %}OLD{% endblock %}']);

        static::assertSame('OLD', $twig->render('base'));
    }

    public function testUnregisteredFutureMajorKeepsBlockAvailable(): void
    {
        $twig = $this->createTwig(['base' => '{## @deprecated tag:v99.0.0 ##}{% block old %}FUTURE{% endblock %}']);

        static::assertSame('FUTURE', $twig->render('base'));
    }

    public function testInvalidRemovalVersionReportsTemplateLocation(): void
    {
        $twig = $this->createTwig(['invalid' => "\n{## @deprecated tag:v6.8 ##}\n{% block old %}OLD{% endblock %}"]);

        $this->expectExceptionObject(AdapterException::invalidDeprecatedBlockDocumentation(3, new Source('', 'invalid')));

        $twig->load('invalid');
    }

    public function testConflictingRemovalAnnotationsAreRejected(): void
    {
        $twig = $this->createTwig(['invalid' => '{## @deprecated tag:v6.8.0 ##}{## @deprecated tag:v6.9.0 ##}{% block old %}{% endblock %}']);

        $this->expectException(SyntaxError::class);

        $twig->load('invalid');
    }

    /**
     * @param array<string, string> $templates
     */
    private function createTwig(array $templates): Environment
    {
        $twig = new Environment(new ArrayLoader($templates), ['cache' => false]);
        $twig->addExtension(new FeatureFlagExtension());

        return $twig;
    }
}
