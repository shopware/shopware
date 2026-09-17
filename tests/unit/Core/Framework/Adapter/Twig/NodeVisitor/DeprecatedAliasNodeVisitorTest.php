<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\NodeVisitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\DeprecatedExtension;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\DeprecatedAliasNodeVisitor;
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
#[CoversClass(DeprecatedAliasNodeVisitor::class)]
class DeprecatedAliasNodeVisitorTest extends TestCase
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

    public function testAliasTriggersWhenReadFromAnExtendingTemplate(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->exactly(2))
            ->method('deprecation')
            ->with('', '', 'The "type" Twig variable is deprecated.');
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'base.html.twig' => <<<'TWIG'
{% set addressType = 'billing' %}
{# @deprecated tag:v6.8.0 - Use `addressType` instead of `type`. #}
{% set type = deprecatedAlias(addressType) %}
{% block content %}{% endblock %}
TWIG,
            'child.html.twig' => <<<'TWIG'
{% extends 'base.html.twig' %}
{% block content %}{{ type|upper }}:{{ type == 'billing' ? 'yes' : 'no' }}{% endblock %}
TWIG,
        ]);

        static::assertSame('BILLING:yes', \trim($twig->render('child.html.twig')));
    }

    public function testUnusedAliasDoesNotTrigger(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% set addressType = 'billing' %}
{# @deprecated tag:v6.8.0 - Use `addressType` instead of `type`. #}
{% set type = deprecatedAlias(addressType) %}
rendered
TWIG,
        ]);

        static::assertSame('rendered', \trim($twig->render('index.html.twig')));
    }

    public function testDefinedCheckDoesNotConsumeAlias(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% set addressType = 'billing' %}
{# @deprecated tag:v6.8.0 - Use `addressType` instead of `type`. #}
{% set type = deprecatedAlias(addressType) %}
{{ type is defined ? 'defined' : 'missing' }}
TWIG,
        ]);

        static::assertSame('defined', \trim($twig->render('index.html.twig')));
    }

    public function testSilentUnwrapDoesNotConsumeAlias(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% set showVatIdField = deprecatedAlias(false) %}
{{ showVatIdField.silentUnwrap() ? 'true' : 'false' }}
TWIG,
        ]);

        static::assertSame('false', \trim($twig->render('index.html.twig')));
    }

    public function testOverwrittenAliasDoesNotTrigger(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% set addressType = 'billing' %}
{# @deprecated tag:v6.8.0 - Use `addressType` instead of `type`. #}
{% set type = deprecatedAlias(addressType) %}
{% set type = 'local' %}
{{ type }}
TWIG,
        ]);

        static::assertSame('local', \trim($twig->render('index.html.twig')));
    }

    public function testFeatureGuardRemovesAlias(): void
    {
        $this->setEnvVars(['V6_8_0_0' => true]);

        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $twig = $this->createTwig([
            'index.html.twig' => <<<'TWIG'
{% set addressType = 'billing' %}
{% if not feature('v6.8.0.0') %}
    {# @deprecated tag:v6.8.0 - Use `addressType` instead of `type`. #}
    {% set type = deprecatedAlias(addressType) %}
{% endif %}
{{ type|default('missing') }}
TWIG,
        ]);

        static::assertSame('missing', \trim($twig->render('index.html.twig')));
    }

    /**
     * @param array<string, string> $templates
     */
    private function createTwig(array $templates): Environment
    {
        $twig = new Environment(new ArrayLoader($templates));
        $twig->addExtension(new DeprecatedExtension());
        $twig->addFunction(new TwigFunction('feature', Feature::isActive(...)));

        return $twig;
    }
}
