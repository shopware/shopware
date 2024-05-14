<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @implements Rule<StaticCall>
 *
 * @internal
 */
#[Package('core')]
class NoAssertsOnObjectsRule implements Rule
{
    private const FORBIDDEN_OBJECTS = [
        Response::class => 'Asserting for equality with Response Objects is not allowed. Responses contain a date time as header, and thus those comparisons are time sensitive and thus flaky. Please assert on the properties of the Response you are interested in directly or use the `AssertResponseHelper`.',
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->getClassReflection() || !TestRuleHelper::isTestClass($scope->getClassReflection())) {
            return [];
        }

        if (!$node->name instanceof Identifier) {
            return [];
        }

        if (!\in_array((string) $node->name, ['assertEquals', 'assertSame'], true)) {
            return [];
        }

        $firstArg = $node->args[0];
        if (!$firstArg instanceof Arg) {
            return [];
        }

        $type = $scope->getType($firstArg->value);

        foreach (self::FORBIDDEN_OBJECTS as $object => $message) {
            if ((new ObjectType($object))->isSuperTypeOf($type)->yes()) {
                return [RuleErrorBuilder::message($message)->identifier('shopware.assertObjects')->build()];
            }
        }

        return [];
    }
}
