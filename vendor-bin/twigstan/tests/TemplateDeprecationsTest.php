<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateDeprecations::class)]
class TemplateDeprecationsTest extends TestCase
{
    public function testUsesTheRegisteredExpressionParsers(): void
    {
        $source = "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% if 1 === 1 %}{{ old }}{% endif %}";
        $twig = new Environment(new ArrayLoader(['custom.twig' => $source]));
        $twig->addExtension(new PhpSyntaxExtension());

        $diagnostics = (new TemplateDeprecations($twig, ['custom.twig']))->getDiagnostics('custom.twig');

        static::assertCount(1, $diagnostics);
        static::assertStringContainsString('Variable "old" is deprecated:', $diagnostics[0]['message']);
    }

    /** @param array<string, string> $templates
     * @param list<array{string, int, string}> $expected
     */
    #[DataProvider('cases')]
    public function testReportsDeprecatedTemplateApis(array $templates, array $expected): void
    {
        $twig = new Environment(new ArrayLoader($templates));
        $twig->addFunction(new TwigFunction('feature', static fn (string $flag): bool => false));
        $deprecations = new TemplateDeprecations($twig, array_keys($templates));
        $actual = [];
        foreach ($templates as $name => $source) {
            foreach ($deprecations->getDiagnostics($name) as $diagnostic) {
                $actual[] = [$name, $diagnostic['line'], $diagnostic['message']];
            }
        }

        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{array<string, string>, list<array{string, int, string}>}>
     */
    public static function cases(): iterable
    {
        yield 'include preserves its matching inactive removal guard' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% if not feature('v6.8.0.0') %}{% include 'child.twig' %}{% endif %}",
            'child.twig' => '{{ old }}',
        ], []];

        yield 'active removal guard does not hide a read in an included template' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% if feature('v6.8.0.0') %}{% include 'child.twig' %}{% endif %}",
            'child.twig' => '{{ old }}',
        ], [
            ['child.twig', 1, 'Variable "old" is deprecated: tag:v6.8.0 - Removed. (declared in "caller.twig").'],
        ]];

        yield 'clean typed inputs are forwarded implicitly to deprecated include inputs' => [[
            'input.twig' => "{% types {\n## @deprecated tag:v6.8.0 - Removed.\nold?: 'string'\n} %}",
            'caller.twig' => "{% types {old: 'string'} %}\n{% include 'input.twig' %}",
        ], [
            ['caller.twig', 2, 'Template input "old" is deprecated: tag:v6.8.0 - Removed. (declared in "input.twig").'],
        ]];

        yield 'nested traits retain the original deprecated declaration' => [[
            'first.twig' => '{## @deprecated tag:v6.8.0 - Removed. ##}{% block old %}Old{% endblock %}',
            'second.twig' => '{% use \'first.twig\' %}',
            'child.twig' => "{% use 'second.twig' %}\n{% block old %}New{% endblock %}",
        ], [
            ['child.twig', 2, 'Block override "old" is deprecated: tag:v6.8.0 - Removed. (declared in "first.twig").'],
        ]];

        yield 'embedded block overrides are checked against their actual parent' => [[
            'base.twig' => '{## @deprecated tag:v6.8.0 - Removed. ##}{% block old %}Old{% endblock %}',
            'child.twig' => "{% embed 'base.twig' %}\n{% block old %}New{% endblock %}{% endembed %}",
        ], [
            ['child.twig', 2, 'Block override "old" is deprecated: tag:v6.8.0 - Removed. (declared in "base.twig").'],
        ]];

        yield 'embedded blocks receive the enclosing variable provenance' => [[
            'base.twig' => '{% block content %}Content{% endblock %}',
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% embed 'base.twig' %}\n{% block content %}{{ old }}{% endblock %}{% endembed %}",
        ], [
            ['caller.twig', 5, 'Variable "old" is deprecated: tag:v6.8.0 - Removed. (declared in "caller.twig").'],
        ]];

        yield 'embed only keeps replacement bindings separate from the enclosing context' => [[
            'base.twig' => '{% block content %}Content{% endblock %}',
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% embed 'base.twig' with {old: 'New'} only %}\n{% block content %}{{ old }}{% endblock %}{% endembed %}",
        ], []];

        yield 'explicit calls resolve the requested external template' => [[
            'base.twig' => '{## @deprecated tag:v6.8.0 - Removed. ##}{% block old %}Old{% endblock %}',
            'caller.twig' => '{{ block(\'old\', \'base.twig\') }}',
        ], [
            ['caller.twig', 1, 'Block call "old" is deprecated: tag:v6.8.0 - Removed. (declared in "base.twig").'],
        ]];

        yield 'external block calls do not inherit deprecation from an unrelated local block' => [[
            'base.twig' => '{## @deprecated tag:v6.8.0 - Removed. ##}{% block old %}Old{% endblock %}',
            'modern.twig' => '{% block old %}Modern{% endblock %}',
            'caller.twig' => '{% extends \'base.twig\' %}{% block content %}{{ block(\'old\', \'modern.twig\') }}{% endblock %}',
        ], []];

        yield 'included blocks with the same name retain their own scope' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% block content %}{% include 'child.twig' %}{% endblock %}",
            'child.twig' => '{% block content %}{{ old }}{% endblock %}',
        ], [
            ['child.twig', 1, 'Variable "old" is deprecated: tag:v6.8.0 - Removed. (declared in "caller.twig").'],
        ]];

        yield 'deprecated input contracts are inherited by included child templates' => [[
            'base.twig' => "{% types {\n## @deprecated tag:v6.8.0 - Removed.\nold?: 'string'\n} %}{% block content %}Content{% endblock %}",
            'child.twig' => '{% extends \'base.twig\' %}',
            'caller.twig' => '{% include \'child.twig\' with {old: \'Old\'} only %}',
        ], [
            ['caller.twig', 1, 'Template input "old" is deprecated: tag:v6.8.0 - Removed. (declared in "base.twig").'],
        ]];

        yield 'deprecated trait block aliases retain their declaration' => [[
            'trait.twig' => '{## @deprecated tag:v6.8.0 - Removed. ##}{% block old %}Old{% endblock %}',
            'child.twig' => "{% use 'trait.twig' with old as legacy %}\n{% block legacy %}Custom{% endblock %}",
        ], [
            ['child.twig', 2, 'Block override "legacy" is deprecated: tag:v6.8.0 - Removed. (declared in "trait.twig").'],
        ]];

        yield 'ordinary comments do not deprecate declarations' => [[
            'base.twig' => '{# @deprecated tag:v6.8.0 - An implementation reminder. #}{% block title %}Title{% endblock %}',
            'child.twig' => '{% extends \'base.twig\' %}{% block title %}Custom{% endblock %}',
        ], []];

        yield 'all branches replacing a deprecated binding clear its provenance' => [[
            'base.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% block content %}{% endblock %}",
            'child.twig' => '{% extends \'base.twig\' %}{% block content %}{% if condition %}{% set old = \'A\' %}{% else %}{% set old = \'B\' %}{% endif %}{{ old }}{% endblock %}',
        ], []];

        yield 'with scopes shadow and then restore an inherited deprecated binding' => [[
            'base.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% block content %}{% endblock %}",
            'child.twig' => "{% extends 'base.twig' %}\n{% block content %}{% with {old: 'New'} only %}{{ old }}{% endwith %}\n{{ old }}{% endblock %}",
        ], [
            ['child.twig', 3, 'Variable "old" is deprecated: tag:v6.8.0 - Removed. (declared in "base.twig").'],
        ]];

        yield 'include function accepts named deprecated input arguments' => [[
            'input.twig' => "{% types {\n## @deprecated tag:v6.8.0 - Removed.\nold?: 'string'\n} %}",
            'caller.twig' => '{{ include(template: \'input.twig\', variables: {old: \'Old\'}, with_context: false) }}',
        ], [
            ['caller.twig', 1, 'Template input "old" is deprecated: tag:v6.8.0 - Removed. (declared in "input.twig").'],
        ]];

        yield 'inactive removal flag in an else branch suppresses compatibility reads' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% if feature('v6.8.0.0') %}New{% else %}{{ old }}{% endif %}",
        ], []];

        yield 'short circuit removal guard protects the variable read' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% if not feature('v6.8.0.0') and old %}Old{% endif %}",
        ], []];

        yield 'active removal flag does not suppress a deprecated read' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% if feature('v6.8.0.0') %}{{ old }}{% endif %}",
        ], [
            ['caller.twig', 4, 'Variable "old" is deprecated: tag:v6.8.0 - Removed. (declared in "caller.twig").'],
        ]];

        yield 'overriding a deprecated block does not require parent()' => [[
            'base.twig' => "{## @deprecated tag:v6.8.0 - Use title instead. ##}\n{% block legacy_title %}Old title{% endblock %}",
            'child.twig' => "{% extends 'base.twig' %}\n{% block legacy_title %}Custom title{% endblock %}",
        ], [
            ['child.twig', 2, 'Block override "legacy_title" is deprecated: tag:v6.8.0 - Use title instead. (declared in "base.twig").'],
        ]];

        yield 'grandparent deprecation survives an unannotated intermediate override' => [[
            'base.twig' => "{## @deprecated tag:v6.8.0 - Use title instead. ##}\n{% block old %}Old{% endblock %}",
            'middle.twig' => "{% extends 'base.twig' %}\n{% block old %}Middle{% endblock %}",
            'child.twig' => "{% extends 'middle.twig' %}\n{% block old %}Child{% endblock %}",
        ], [
            ['middle.twig', 2, 'Block override "old" is deprecated: tag:v6.8.0 - Use title instead. (declared in "base.twig").'],
            ['child.twig', 2, 'Block override "old" is deprecated: tag:v6.8.0 - Use title instead. (declared in "base.twig").'],
        ]];

        yield 'matching names in unrelated templates are not deprecated' => [[
            'old.twig' => '{## @deprecated tag:v6.8.0 - Removed. ##}{% block title %}Old{% endblock %}',
            'other.twig' => '{% block title %}Other{% endblock %}',
            'child.twig' => '{% extends \'other.twig\' %}{% block title %}Child{% endblock %}',
        ], []];

        yield 'inherited variable reads refer to their original binding' => [[
            'base.twig' => "{% set\n## @deprecated tag:v6.8.0 - Use label instead.\noldLabel = 'Old'\n%}\n{% block title %}Title{% endblock %}",
            'child.twig' => "{% extends 'base.twig' %}\n{% block title %}{{ oldLabel }}{% endblock %}",
        ], [
            ['child.twig', 2, 'Variable "oldLabel" is deprecated: tag:v6.8.0 - Use label instead. (declared in "base.twig").'],
        ]];

        yield 'nested overrides inherit assignments from the containing parent block' => [[
            'base.twig' => "{% block outer %}\n{% set\n## @deprecated tag:v6.8.0 - Use label instead.\noldLabel = 'Old'\n%}\n{% block inner %}Inner{% endblock %}{% endblock %}",
            'child.twig' => "{% extends 'base.twig' %}\n{% block inner %}{{ oldLabel }}{% endblock %}",
        ], [
            ['child.twig', 2, 'Variable "oldLabel" is deprecated: tag:v6.8.0 - Use label instead. (declared in "base.twig").'],
        ]];

        yield 'a clean reassignment does not deprecate the new binding' => [[
            'base.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nvalue = 'Old'\n%}{% block content %}{% endblock %}",
            'child.twig' => '{% extends \'base.twig\' %}{% block content %}{% set value = \'New\' %}{{ value }}{% endblock %}',
        ], []];

        yield 'an alias reports its deprecated source without tainting the alias' => [[
            'base.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% block content %}{% endblock %}",
            'child.twig' => "{% extends 'base.twig' %}\n{% block content %}{% set label = old %}{{ label }}{% endblock %}",
        ], [
            ['child.twig', 2, 'Variable "old" is deprecated: tag:v6.8.0 - Removed. (declared in "base.twig").'],
        ]];

        yield 'an include inherits deprecated context bindings' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% include 'child.twig' %}",
            'child.twig' => '{{ old }}',
        ], [
            ['child.twig', 1, 'Variable "old" is deprecated: tag:v6.8.0 - Removed. (declared in "caller.twig").'],
        ]];

        yield 'include only discards inherited deprecated bindings' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% include 'child.twig' with {old: 'New'} only %}",
            'child.twig' => '{{ old }}',
        ], []];

        yield 'explicit include arguments replace deprecated context bindings' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% include 'child.twig' with {old: 'New'} %}",
            'child.twig' => '{{ old }}',
        ], []];

        yield 'caller supplies a deprecated input declared through types' => [[
            'input.twig' => "{% types {\n## @deprecated tag:v6.8.0 - Use label instead.\nold?: 'string', label?: 'string'\n} %}{{ label|default('') }}",
            'caller.twig' => "{% include 'input.twig' with {old: 'Old'} only %}\n{% include 'input.twig' with {label: 'New'} only %}",
        ], [
            ['caller.twig', 1, 'Template input "old" is deprecated: tag:v6.8.0 - Use label instead. (declared in "input.twig").'],
        ]];

        yield 'include function checks deprecated input arguments' => [[
            'input.twig' => "{% types {\n## @deprecated tag:v6.8.0 - Removed.\nold?: 'string'\n} %}",
            'caller.twig' => '{{ include(\'input.twig\', {old: \'Old\'}, false) }}',
        ], [
            ['caller.twig', 1, 'Template input "old" is deprecated: tag:v6.8.0 - Removed. (declared in "input.twig").'],
        ]];

        yield 'downstream reads an inherited deprecated input' => [[
            'base.twig' => "{% types {\n## @deprecated tag:v6.8.0 - Removed.\nold?: 'string'\n} %}{% block content %}{% endblock %}",
            'child.twig' => "{% extends 'base.twig' %}\n{% block content %}{{ old }}{% endblock %}",
        ], [
            ['child.twig', 2, 'Variable "old" is deprecated: tag:v6.8.0 - Removed. (declared in "base.twig").'],
        ]];

        yield 'a loop target shadows an inherited deprecated variable' => [[
            'base.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nitem = 'Old'\n%}{% block content %}{% endblock %}",
            'child.twig' => '{% extends \'base.twig\' %}{% block content %}{% for item in [\'New\'] %}{{ item }}{% endfor %}{% endblock %}',
        ], []];

        yield 'inactive removal flag suppresses variable and input compatibility reads' => [[
            'input.twig' => "{% types {\n## @deprecated tag:v6.8.0 - Removed.\nold?: 'string'\n} %}",
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% if not feature('v6.8.0.0') %}{{ old }}{% include 'input.twig' with {old: 'Old'} only %}{% endif %}",
        ], []];

        yield 'named feature flags do not prove the removal version is inactive' => [[
            'caller.twig' => "{% set\n## @deprecated tag:v6.8.0 - Removed.\nold = 'Old'\n%}{% if not feature('SOME_FEATURE') %}{{ old }}{% endif %}",
        ], [
            ['caller.twig', 4, 'Variable "old" is deprecated: tag:v6.8.0 - Removed. (declared in "caller.twig").'],
        ]];

        yield 'a guarded override body still depends on its deprecated block' => [[
            'base.twig' => '{## @deprecated tag:v6.8.0 - Removed. ##}{% block old %}Old{% endblock %}',
            'child.twig' => "{% extends 'base.twig' %}\n{% block old %}{% if not feature('v6.8.0.0') %}Custom{% endif %}{% endblock %}",
        ], [
            ['child.twig', 2, 'Block override "old" is deprecated: tag:v6.8.0 - Removed. (declared in "base.twig").'],
        ]];
    }
}
