<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * Collects the calls that make a Twig environment render (and thereby resolve its globals) inside test classes
 * of the enabled namespaces: `render()`/`display()` on the environment, and the render calls of a template it
 * handed out through `load()`, `createTemplate()` or `resolveTemplate()`. Handing out a template alone resolves
 * nothing, so a test that only compiles or inspects templates is not collected.
 *
 * @phpstan-type TwigRenderData array{class: string, hierarchy: list<string>, call: string, line: int, shared: bool, key: string|null}
 *
 * @implements Collector<MethodCall, TwigRenderData>
 *
 * @internal
 */
#[Package('framework')]
class TwigRenderCollector implements Collector
{
    private const ENVIRONMENT_RENDER_METHODS = ['render', 'display'];

    private const TEMPLATE_RENDER_METHODS = ['render', 'display', 'renderblock', 'displayblock'];

    /**
     * Narrows the rule to matching test namespaces; an empty list disables it. Consumers grow the list via
     * the `shopware.sharedTwigRenderEnabledNamespaces` parameter of their PHPStan config.
     *
     * @var list<string>
     */
    private readonly array $enabledNamespaces;

    public function __construct(Configuration $configuration)
    {
        $this->enabledNamespaces = $configuration->getSharedTwigRenderEnabledNamespaces();
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     *
     * @return TwigRenderData|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node->name instanceof Identifier) {
            return null;
        }

        $method = $node->name->toLowerString();
        if (\in_array($method, self::ENVIRONMENT_RENDER_METHODS, true) && SharedTwigOrigin::isEnvironment($node->var, $scope)) {
            $environment = $node->var;
            $call = 'Twig\\Environment::' . $node->name->name . '()';
        } elseif (\in_array($method, self::TEMPLATE_RENDER_METHODS, true) && SharedTwigOrigin::isTemplateWrapper($node->var, $scope)) {
            // `$twig->createTemplate('...')->render()` renders through $twig; `$template->render()` through the
            // reference, whose assignment the origin collector records with the origin of its environment
            $environment = SharedTwigOrigin::templateSource($node->var, $scope) ?? $node->var;
            $call = 'Twig\\TemplateWrapper::' . $node->name->name . '()';
        } else {
            return null;
        }

        $class = $scope->getClassReflection();
        if ($class === null || !TestRuleHelper::isTestClass($class) || !$this->isEnabledNamespace($class->getName())) {
            return null;
        }

        if (SharedTwigOrigin::isNewEnvironment($environment, $scope)) {
            return null;
        }

        $shared = SharedTwigOrigin::isSharedTwigLookup($environment, $scope);
        $key = $shared ? null : SharedTwigOrigin::referenceKey($environment, $scope);
        if (!$shared && $key === null) {
            // rendered through something the rule cannot trace (a helper's return value, a parameter, ...)
            return null;
        }

        return [
            'class' => $class->getName(),
            'hierarchy' => [$class->getName(), ...$class->getParentClassesNames()],
            'call' => $call,
            'line' => $node->getStartLine(),
            'shared' => $shared,
            'key' => $key,
        ];
    }

    private function isEnabledNamespace(string $className): bool
    {
        foreach ($this->enabledNamespaces as $namespace) {
            if (str_starts_with($className, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
