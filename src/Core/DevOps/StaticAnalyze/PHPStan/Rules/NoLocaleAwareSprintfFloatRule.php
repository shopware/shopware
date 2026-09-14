<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<FuncCall>
 */
#[Package('framework')]
final class NoLocaleAwareSprintfFloatRule implements Rule
{
    private const ERROR_MESSAGE = 'Do not use the locale-aware %f specifier in sprintf(). Use %F for locale-independent float serialization.';

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name || strtolower($node->name->toString()) !== 'sprintf' || $node->args === []) {
            return [];
        }

        if (!$this->reflectionProvider->hasFunction($node->name, $scope) || !$this->reflectionProvider->getFunction($node->name, $scope)->isBuiltin()) {
            return [];
        }

        $formatArgument = $node->args[0];
        if (!$formatArgument instanceof Arg) {
            return [];
        }

        foreach ($scope->getType($formatArgument->value)->getConstantStrings() as $format) {
            if (self::containsLocaleAwareFloatSpecifier($format->getValue())) {
                return [
                    RuleErrorBuilder::message(self::ERROR_MESSAGE)
                        ->identifier('shopware.noLocaleAwareSprintfFloat')
                        ->build(),
                ];
            }
        }

        return [];
    }

    private static function containsLocaleAwareFloatSpecifier(string $format): bool
    {
        $offset = 0;
        while (($percent = strpos($format, '%', $offset)) !== false) {
            $percentRunLength = strspn($format, '%', $percent);
            if ($percentRunLength % 2 === 0) {
                $offset = $percent + $percentRunLength;

                continue;
            }

            $conversion = substr($format, $percent + $percentRunLength - 1);
            if (preg_match('~^%(?:\\d+\$)?(?>(?:[-+ 0]|\'[\\s\\S])*)(?:\\*|\\d+)?(?:\\.(?:\\*|\\d+))?f~', $conversion) === 1) {
                return true;
            }

            $offset = $percent + $percentRunLength;
        }

        return false;
    }
}
