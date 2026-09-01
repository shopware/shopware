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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * Bans kernel boots in the static PHPUnit lifecycle hooks.
 *
 * A deprecation triggered during a static-context kernel boot (the container compile is the
 * deprecation hotspot) has no TestCase object on the call stack: when a previous test leaked
 * PHPUnit's per-test error handler, the handler throws NoTestCaseObjectOnCallStackException and
 * the run dies with an order-dependent, junit-invisible crash instead of a recorded deprecation.
 *
 * Boot lazily inside a test context instead: keep a static "booted" flag and boot in setUp() of
 * the first test; tearDownAfterClass() only needs ensureKernelShutdown(), the next class boots
 * its own kernel.
 *
 * @implements Rule<StaticCall>
 *
 * @internal
 */
#[Package('framework')]
class NoKernelBootInSetUpBeforeClassRule implements Rule
{
    public const ERROR_STATIC_BOOT = 'Do not boot a kernel in a static PHPUnit lifecycle hook: a deprecation triggered during the boot has no TestCase on the call stack and crashes the run when a leaked error handler is active. Boot lazily in setUp() behind a static flag, and only call ensureKernelShutdown() in tearDownAfterClass().';

    private const BANNED_METHODS = ['bootKernel' => true, 'createKernel' => true];

    private const STATIC_HOOKS = ['setUpBeforeClass' => true, 'tearDownAfterClass' => true];

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
        if (!$scope->getClassReflection() || !TestRuleHelper::isTestClass($scope->getClassReflection())) {
            return [];
        }

        $method = $scope->getFunction();
        if ($method === null || !isset(self::STATIC_HOOKS[$method->getName()])) {
            return [];
        }

        if (!$node->name instanceof Identifier || !isset(self::BANNED_METHODS[$node->name->name])) {
            return [];
        }

        if (!$node->class instanceof Name || $scope->resolveName($node->class) !== KernelLifecycleManager::class) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::ERROR_STATIC_BOOT)
                ->identifier('shopware.kernelBootInStaticLifecycleHook')
                ->build(),
        ];
    }
}
