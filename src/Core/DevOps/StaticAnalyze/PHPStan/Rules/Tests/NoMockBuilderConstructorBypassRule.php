<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Discourages the legacy `getMockBuilder(X)->disableOriginalConstructor()->getMock()` idiom.
 *
 * `createStub()` / `createMock()` already bypass the original constructor, so the builder is
 * redundant. When `onlyMethods()`/`addMethods()` is chained it builds a *partial* mock, which is
 * only justified if the un-doubled (real) methods are actually exercised — otherwise it collapses
 * to `createStub()`/`createMock()` too.
 *
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoMockBuilderConstructorBypassRule implements Rule
{
    public const ERROR_REDUNDANT = 'getMockBuilder()->disableOriginalConstructor()->getMock() is redundant: createStub() and createMock() already bypass the original constructor. Use createStub() for a pure test double, or createMock() when you configure expectations.';

    public const ERROR_PARTIAL = 'getMockBuilder()->disableOriginalConstructor()->onlyMethods(...)->getMock() builds a partial mock with a bypassed constructor. Only keep it if you deliberately rely on the real implementations of the un-doubled methods; otherwise the real methods run against an uninitialized object — prefer createStub()/createMock().';

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

        if (!$isMockBuilderChain || !isset($builderMethods['disableOriginalConstructor'])) {
            return [];
        }

        $isPartial = isset($builderMethods['onlyMethods']) || isset($builderMethods['addMethods']);

        return [
            RuleErrorBuilder::message($isPartial ? self::ERROR_PARTIAL : self::ERROR_REDUNDANT)
                ->identifier($isPartial ? 'shopware.mockBuilderPartialConstructorBypass' : 'shopware.mockBuilderConstructorBypass')
                ->build(),
        ];
    }
}
