<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<MethodCall>
 */
#[Package('framework')]
class NoLegacySqlHashFunctionRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !($node->name instanceof Identifier)) {
            return [];
        }

        $methodName = $node->name->toString();
        if (!\in_array($methodName, ['executeQuery', 'executeStatement', 'prepare'], true)) {
            return [];
        }

        $varType = $scope->getType($node->var);
        if (!\in_array(Connection::class, $varType->getObjectClassNames(), true)) {
            return [];
        }

        if ($node->args === []) {
            return [];
        }

        $firstArgNode = $node->args[0];
        if (!$firstArgNode instanceof Node\Arg) {
            return [];
        }

        $firstArg = $firstArgNode->value;
        // Only compile-time constant SQL strings are inspected; dynamic SQL (sprintf, concatenation, variables)
        // is not analyzed, which is a PHPStan limitation we accept here.
        $constantStrings = $scope->getType($firstArg)->getConstantStrings();

        foreach ($constantStrings as $constantString) {
            $sql = $constantString->getValue();
            if (!preg_match('/\b(md5|sha1)\s*\(/i', $sql)) {
                continue;
            }

            return [
                RuleErrorBuilder::message(
                    \sprintf(
                        'Legacy SQL hash function detected in `%s()`. Do not use MD5()/SHA1() in SQL, use SHA2() or compute hash in PHP.',
                        $methodName
                    )
                )->identifier('shopware.noLegacySqlHash')->build(),
            ];
        }

        return [];
    }
}
