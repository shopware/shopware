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
        foreach (['method.deprecatedInterface', 'classConstant.deprecated', 'classConstant.deprecatedClass', 'classConstant.deprecatedInterface', 'property.deprecated', 'property.deprecatedClass', 'catch.deprecatedClass', 'instanceof.deprecatedClass'] as $identifier) {
            yield $identifier . ' in matching legacy branch' => [$identifier, 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        }
        yield 'silent inactive callback' => ['method.deprecated', 'v6.8.0', 'Feature::callSilentIfInactive("v6.8.0.0", static function () use ($api) { $api->old(); });', true];
        yield 'inactive arrow callback' => ['method.deprecated', 'v6.8.0', 'Feature::ifNotActive("v6.8.0.0", static fn () => $api->old());', true];
        yield 'inactive callback with wrong version' => ['method.deprecated', 'v6.8.0', 'Feature::callSilentIfInactive("v6.9.0.0", static fn () => $api->old());', false];
        yield 'inactive callback with dynamic flag' => ['method.deprecated', 'v6.8.0', 'Feature::callSilentIfInactive($flag, static fn () => $api->old());', false];
        yield 'inactive callback with named flag only' => ['method.deprecated', 'v6.8.0', 'Feature::ifNotActive("TWIGSTAN_LEGACY_TEST", static fn () => $api->old());', false];
        yield 'active callback is not a legacy guard' => ['method.deprecated', 'v6.8.0', 'Feature::ifActive("v6.8.0.0", static fn () => $api->old());', false];
        yield 'silencing alone does not prove inactive' => ['method.deprecated', 'v6.8.0', 'Feature::silent("v6.8.0.0", static fn () => $api->old());', false];
        yield 'nested deferred callback is not covered' => ['method.deprecated', 'v6.8.0', 'Feature::callSilentIfInactive("v6.8.0.0", static fn () => static fn () => $api->old());', false];
        yield 'unrelated inactive wrapper is not covered' => ['method.deprecated', 'v6.8.0', 'Other::callSilentIfInactive("v6.8.0.0", static fn () => $api->old());', false];
        yield 'wrapper callable reference does not interfere' => ['method.deprecated', 'v6.8.0', '$callback = Feature::callSilentIfInactive(...); Feature::callSilentIfInactive("v6.8.0.0", static fn () => $api->old());', true];
        yield 'feature callable reference is not a guard' => ['method.deprecated', 'v6.8.0', '$check = Feature::isActive(...); if (!$check) { $api->old(); }', false];
        yield 'matching major is disabled' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'deprecated class with four-part version' => ['method.deprecatedClass', 'v6.8.0.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'deprecated static method' => ['staticMethod.deprecated', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'static method of deprecated class' => ['staticMethod.deprecatedClass', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'deprecated constructor' => ['new.deprecatedClass', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $api->old(); }', true];
        yield 'enabled feature branch' => ['method.deprecated', 'v6.8.0', 'if (Feature::isActive("v6.8.0.0")) { $api->old(); }', false];
        yield 'wrong major' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("v6.9.0.0")) { $api->old(); }', false];
        yield 'named feature can override its major' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("TWIGSTAN_LEGACY_TEST")) { $api->old(); }', false];
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
        yield 'reference exists before alias assignment' => ['method.deprecated', 'v6.8.0', '$ref = &$new; $new = Feature::isActive("v6.8.0.0"); $ref = false; if (!$new) { $api->old(); }', false];
        yield 'array reference exists before alias assignment' => ['method.deprecated', 'v6.8.0', '$refs = [&$new]; $new = Feature::isActive("v6.8.0.0"); $refs[0] = false; if (!$new) { $api->old(); }', false];
        yield 'reference to context exists before alias assignment' => ['method.deprecated', 'v6.8.0', '$ref = &$context; $context["new"] = Feature::isActive("v6.8.0.0"); $ref["new"] = false; if (!$context["new"]) { $api->old(); }', false];
        yield 'alias is captured by reference' => ['method.deprecated', 'v6.8.0', '$callback = function () use (&$new) { $new = false; }; $new = Feature::isActive("v6.8.0.0"); $callback(); if (!$new) { $api->old(); }', false];
        yield 'context is iterated by reference' => ['method.deprecated', 'v6.8.0', 'foreach ($context as &$ref) {} $context["new"] = Feature::isActive("v6.8.0.0"); $ref = false; if (!$context["new"]) { $api->old(); }', false];
        yield 'alias is overwritten inside condition' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); if (($new = false) || $new) {} else { $api->old(); }', false];
        yield 'alias is mutated inside another alias RHS' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); $copy = mutate($new) || $new; if (!$copy) { $api->old(); }', false];
        yield 'alias may be changed by a call' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); mutate($new); if (!$new) { $api->old(); }', false];
        yield 'alias decremented' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0"); --$new; if (!$new) { $api->old(); }', false];
        yield 'dynamic context write invalidates aliases' => ['method.deprecated', 'v6.8.0', '$context["new"] = Feature::isActive("v6.8.0.0"); $context[$key] = false; if (!$context["new"]) { $api->old(); }', false];
        yield 'nested dynamic write invalidates aliases' => ['method.deprecated', 'v6.8.0', '$context["flags"]["new"] = Feature::isActive("v6.8.0.0"); $context[$key]["new"] = false; if (!$context["flags"]["new"]) { $api->old(); }', false];
        yield 'false conjunction does not prove feature disabled' => ['method.deprecated', 'v6.8.0', '$new = Feature::isActive("v6.8.0.0") && $enabled; if (!$new) { $api->old(); }', false];
        yield 'legacy ternary' => ['method.deprecated', 'v6.8.0', '$value = Feature::isActive("v6.8.0.0") ? "new" : $api->old();', true];
        yield 'short-circuit legacy call' => ['method.deprecated', 'v6.8.0', '!Feature::isActive("v6.8.0.0") && $api->old();', true];
        yield 'closure has its own scope' => ['method.deprecated', 'v6.8.0', 'if (!Feature::isActive("v6.8.0.0")) { $callback = function () { $api->old(); }; }', false];
        yield 'compiled Twig major feature guard' => ['method.deprecated', 'v6.8.0', 'if (!$this->getExtension(\'' . FeatureFlagExtension::class . '\')->feature("v6.8.0.0")) { $api->old(); }', true];
    }
}
