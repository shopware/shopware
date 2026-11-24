<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\FeatureGuardedDeprecationExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
class FeatureGuardedDeprecationExtensionTest extends TestCase
{
    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $features;

    protected function setUp(): void
    {
        $this->features = Feature::getRegisteredFeatures();
        Feature::registerFeature('TWIGSTAN_LEGACY_TEST', ['major' => 'v6.8.0.0']);
        Feature::registerFeature('TWIGSTAN_FUTURE_TEST', ['major' => 'v6.9.0.0']);
    }

    protected function tearDown(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->features);
    }

    #[DataProvider('guards')]
    public function testOnlyAcceptsDefiniteLegacyGuardsForTheRemovalVersion(string $identifier, string $version, string $body, bool $expected): void
    {
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php use Shopware\Core\Framework\Feature; function render(): void {' . $body . '}');
        static::assertNotNull($nodes);
        $nodes = (new NodeTraverser(new NameResolver()))->traverse($nodes);
        $target = (new NodeFinder())->findFirst($nodes, static fn (Node $node): bool => $node instanceof MethodCall && $node->name instanceof Node\Identifier && $node->name->toString() === 'old');
        static::assertNotNull($target);
        $parser = static::createStub(Parser::class);
        $parser->method('parseFile')->willReturn($nodes);
        $scope = static::createStub(Scope::class);
        $scope->method('getFile')->willReturn('template.php');
        // @phpstan-ignore phpstanApi.constructor (Test fixture for the pinned PHPStan version.)
        $error = new Error('Deprecated API. ' . ($version !== '' ? 'tag:' . $version : ''), 'template.php', identifier: $identifier);

        static::assertSame($expected, (new FeatureGuardedDeprecationExtension($parser))->shouldIgnore($error, $target, $scope));
    }

    /**
     * @return iterable<string, array{string, string, string, bool}>
     */
    public static function guards(): iterable
    {
        yield 'matching major is disabled' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'deprecated class with four-part version' => ['method.deprecatedClass', 'v6.8.0.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'deprecated static method' => ['staticMethod.deprecated', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'static method of deprecated class' => ['staticMethod.deprecatedClass', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'deprecated constructor' => ['new.deprecatedClass', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'enabled feature branch' => ['method.deprecated', 'v6.8.0', 'if (Feature::isActive("v6.8.0.0")) { $api->old(); }', false];
        yield 'wrong major' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("v6.9.0.0")) { $api->old(); }', false];
        yield 'registered feature major' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("TWIGSTAN_LEGACY_TEST")) { $api->old(); }', true];
        yield 'registered feature of another major' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("TWIGSTAN_FUTURE_TEST")) { $api->old(); }', false];
        yield 'unknown feature' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("TWIGSTAN_UNKNOWN_TEST")) { $api->old(); }', false];
        yield 'unguarded call' => ['method.deprecated', 'v6.8.0', '$api->old();', false];
        yield 'closed guard does not cover later call' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) {} $api->old();', false];
        yield 'partial guard does not cover every path' => ['method.deprecated', 'v6.8.0', 'if ($enabled) { if (Feature::isActive("v6.8.0.0")) { return; } } $api->old();', false];
        yield 'missing removal version' => ['method.deprecated', '', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', false];
        yield 'unrelated diagnostic' => ['method.notFound', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', false];
        yield 'patch version is not a major flag' => ['method.deprecated', 'v6.8.1', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', false];
        yield 'aliased alternatives for same major' => ['method.deprecated', 'v6.8.0', '$context["new"] = Feature::isActive("v6.8.0.0") || Feature::isActive("TWIGSTAN_LEGACY_TEST"); if (!$context["new"]) { $api->old(); }', true];
        yield 'alias overwritten' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); $new = $enabled; if (!$new) { $api->old(); }', false];
        yield 'alias conditionally overwritten' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); if ($enabled) { $new = false; } if (!$new) { $api->old(); }', false];
        yield 'alias removed' => ['method.deprecated', 'v6.8.0', '$context["new"] = Feature::isActive("v6.8.0.0"); unset($context["new"]); if (!$context["new"]) { $api->old(); }', false];
        yield 'alias changed through compound assignment' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); $new &= $enabled; if (!$new) { $api->old(); }', false];
        yield 'alias exposed by reference' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); $ref = &$new; $ref = false; if (!$new) { $api->old(); }', false];
        yield 'alias may be changed by a call' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); mutate($new); if (!$new) { $api->old(); }', false];
        yield 'alias decremented' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); --$new; if (!$new) { $api->old(); }', false];
        yield 'dynamic context write invalidates aliases' => ['method.deprecated', 'v6.8.0', '$context["new"] = Feature::isActive("v6.8.0.0"); $context[$key] = false; if (!$context["new"]) { $api->old(); }', false];
        yield 'nested dynamic write invalidates aliases' => ['method.deprecated', 'v6.8.0', '$context["flags"]["new"] = Feature::isActive("v6.8.0.0"); $context[$key]["new"] = false; if (!$context["flags"]["new"]) { $api->old(); }', false];
        yield 'false conjunction does not prove feature disabled' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0") && $enabled; if (!$new) { $api->old(); }', false];
        yield 'legacy ternary' => ['method.deprecated', 'v6.8.0', '$value = Feature::isActive("v6.8.0.0") ? "new" : $api->old();', true];
        yield 'short-circuit legacy call' => ['method.deprecated', 'v6.8.0', '!Feature::isActive("v6.8.0.0") && $api->old();', true];
        yield 'closure has its own scope' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $callback = function () { $api->old(); }; }', false];
        yield 'compiled Twig feature guard' => ['method.deprecated', 'v6.8.0', 'if (!$this->getExtension(\'' . FeatureFlagExtension::class . '\')->feature("TWIGSTAN_LEGACY_TEST")) { $api->old(); }', true];
    }
}
