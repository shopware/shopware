<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * The call-site half of {@see NoKernelInUnitTestsRule}: a unit test never reaches `KernelLifecycleManager`.
 * Checking the static call itself, in the scope PHPStan analyses it in, covers a call placed in the test
 * class, in a trait the test class composes and in a parent test class alike, which a walk over the class
 * body alone would miss. Shutting a kernel down is exempt: it never boots one, and `EnvTestBehaviour` does
 * it after every test to drop a kernel that cached the environment.
 *
 * @implements Rule<StaticCall>
 *
 * @internal
 */
#[Package('framework')]
class NoKernelLifecycleManagerInUnitTestsRule implements Rule
{
    public const ERROR_LIFECYCLE_MANAGER = 'Unit test calls KernelLifecycleManager::%s(), which boots the kernel and needs a database; it only passes because the unit CI job provides one. Move the test to tests/integration, or build the subject with its constructor and test doubles.';
    /**
     * Tears down a booted kernel and is a no-op without one; the only lifecycle call that never boots.
     */
    private const SHUTDOWN_METHOD = 'ensureKernelShutdown';

    /**
     * @var list<string>
     */
    private readonly array $enabledNamespaces;

    public function __construct(Configuration $configuration)
    {
        $this->enabledNamespaces = $configuration->getKernelInUnitTestsEnabledNamespaces();
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Name || !$node->name instanceof Identifier) {
            return [];
        }

        if ($scope->resolveName($node->class) !== KernelLifecycleManager::class || $node->name->name === self::SHUTDOWN_METHOD) {
            return [];
        }

        // inside a trait PHPStan analyses the body once per composing class, so the scope class is the test
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || !TestRuleHelper::isTestClass($classReflection) || !$this->isEnabledNamespace($classReflection->getName())) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(self::ERROR_LIFECYCLE_MANAGER, $node->name->name))
                ->identifier('shopware.kernelInUnitTest')
                ->build(),
        ];
    }

    private function isEnabledNamespace(string $className): bool
    {
        foreach ($this->enabledNamespaces as $namespace) {
            if (\str_contains($className, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
