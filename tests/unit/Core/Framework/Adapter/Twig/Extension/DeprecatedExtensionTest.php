<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\DeprecatedExtension;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\DeprecatedAliasNodeVisitor;
use Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedAlias;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Triggerer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(DeprecatedExtension::class)]
class DeprecatedExtensionTest extends TestCase
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
        Feature::registerFeature('v6.8.0.0', ['major' => true]);
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

    public function testRegistersAliasSupportAndDeprecationFunction(): void
    {
        $extension = new DeprecatedExtension();

        static::assertContainsOnlyInstancesOf(DeprecatedAliasNodeVisitor::class, $extension->getNodeVisitors());
        static::assertContainsOnlyInstancesOf(TwigFunction::class, $extension->getFunctions());
        static::assertSame(['deprecatedAlias', 'sw_trigger_deprecation'], \array_map(
            static fn (TwigFunction $function): string => $function->getName(),
            $extension->getFunctions(),
        ));
        static::assertEquals(new DeprecatedAlias('value'), $extension->deprecatedAlias('value'));
    }

    public function testTriggersDeprecationForInactiveRemovalFeature(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->once())
            ->method('deprecation')
            ->with('', '', 'Use the replacement input.');
        Feature::$triggerer = $triggerer;

        (new DeprecatedExtension())->triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement input.');
    }

    public function testTemplateTriggersOnlyWhenUsingLegacyFallback(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->once())
            ->method('deprecation')
            ->with('', '', 'Use "name" instead.');
        Feature::$triggerer = $triggerer;

        $twig = new Environment(new ArrayLoader([
            'index.html.twig' => <<<'TWIG'
{% set name = name|default(null) %}
{% if not feature('v6.8.0.0') and name is null and snippet_name|default(null) is not null %}
    {# @deprecated tag:v6.8.0 - Use `name` instead of `snippet_name`. #}
    {% do sw_trigger_deprecation('v6.8.0.0', 'Use "name" instead.') %}
    {% set name = snippet_name %}
{% endif %}
{{ name }}
TWIG,
        ]));
        $twig->addExtension(new DeprecatedExtension());
        $twig->addFunction(new TwigFunction('feature', Feature::isActive(...)));

        static::assertSame('legacy', \trim($twig->render('index.html.twig', ['snippet_name' => 'legacy'])));
        static::assertSame('current', \trim($twig->render('index.html.twig', ['name' => 'current', 'snippet_name' => 'legacy'])));

        $this->setEnvVars(['V6_8_0_0' => true]);

        static::assertSame('', \trim($twig->render('index.html.twig', ['snippet_name' => 'legacy'])));
    }
}
