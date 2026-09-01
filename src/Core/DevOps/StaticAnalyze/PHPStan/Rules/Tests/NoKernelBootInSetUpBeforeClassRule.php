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
 * A kernel booted in setUpBeforeClass()/tearDownAfterClass() has no TestCase on the call stack,
 * so a deprecation during the container compile crashes the run under a leaked error handler
 * (NoTestCaseObjectOnCallStackException) instead of being recorded. Boot lazily in setUp().
 *
 * @implements Rule<StaticCall>
 *
 * @internal
 */
#[Package('framework')]
class NoKernelBootInSetUpBeforeClassRule implements Rule
{
    public const ERROR_STATIC_BOOT = 'Do not boot a kernel in a static PHPUnit lifecycle hook: a deprecation during the boot crashes the run instead of being recorded. Boot lazily in setUp() behind a static flag; tearDownAfterClass() only needs ensureKernelShutdown().';

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
