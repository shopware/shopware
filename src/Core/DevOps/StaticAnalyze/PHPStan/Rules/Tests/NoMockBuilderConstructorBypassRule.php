<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Discourages the redundant `getMockBuilder(X)->getMock()` idiom (with or without
 * `disableOriginalConstructor()`).
 *
 * `createStub()` / `createMock()` already bypass the original constructor and double every method,
 * so a bare builder adds nothing — use them instead. The builder is only justified for a *partial*
 * mock (`onlyMethods()`/`addMethods()`) or when you must pass real constructor arguments
 * (`setConstructorArgs()`); those chains are intentionally NOT flagged. (Partials in particular are
 * a legitimate pattern, and our PHPStan CI has no warning level, so an advisory error would only get
 * baselined or suppressed rather than acted on.)
 *
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoMockBuilderConstructorBypassRule implements Rule
{
    public const ERROR_REDUNDANT = 'getMockBuilder()->getMock() is redundant: use createStub() for a pure test double, or createMock() when you configure expectations (both already bypass the original constructor). Keep getMockBuilder() only for a partial mock (onlyMethods()) or when you must pass real constructor arguments (setConstructorArgs()).';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->getClassReflection() || !TestRuleHelper::isTestClass($scope->getClassReflection())) {
            return [];
        }

        if (!$node->name instanceof Identifier || $node->name->name !== 'getMock') {
            return [];
        }

        // Walk down the fluent receiver chain, collecting builder method names until getMockBuilder().
        $builderMethods = [];
        $isMockBuilderChain = false;
        $cursor = $node->var;

        while ($cursor instanceof MethodCall && $cursor->name instanceof Identifier) {
            if ($cursor->name->name === 'getMockBuilder') {
                $isMockBuilderChain = true;

                break;
            }

            $builderMethods[$cursor->name->name] = true;
            $cursor = $cursor->var;
        }

        // getMockBuilder() is invoked either as $this->getMockBuilder() (a MethodCall, handled above)
        // or as static::/self::/parent::getMockBuilder() (a StaticCall) — the latter ends the chain
        // and must be matched too, otherwise those call sites are silently skipped.
        if (!$isMockBuilderChain
            && $cursor instanceof StaticCall
            && $cursor->name instanceof Identifier
            && $cursor->name->name === 'getMockBuilder'
        ) {
            $isMockBuilderChain = true;
        }

        if (!$isMockBuilderChain) {
            return [];
        }

        // Partial mocks (onlyMethods/addMethods) and real-constructor builds (setConstructorArgs)
        // cannot be expressed by createStub()/createMock(), so leave them alone.
        foreach (['onlyMethods', 'addMethods', 'setConstructorArgs'] as $justifiedBuilderCall) {
            if (isset($builderMethods[$justifiedBuilderCall])) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(self::ERROR_REDUNDANT)
                ->identifier('shopware.mockBuilderConstructorBypass')
                ->build(),
        ];
    }
}
