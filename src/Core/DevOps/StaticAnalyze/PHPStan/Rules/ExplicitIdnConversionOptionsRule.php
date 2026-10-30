<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\BitwiseOr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Requires explicit non-transitional UTS #46 options so IDN conversion stays consistent across ICU and polyfill versions.
 * Transitional conversion can map distinct domains, such as "straße.de" and "strasse.de", to the same ASCII domain.
 *
 * @internal
 *
 * @implements Rule<FuncCall>
 */
#[Package('framework')]
final class ExplicitIdnConversionOptionsRule implements Rule
{
    /**
     * @var array<string, int>
     */
    private const PARAMETER_POSITIONS = [
        'domain' => 0,
        'flags' => 1,
        'variant' => 2,
        'idna_info' => 3,
    ];

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name || !$this->reflectionProvider->hasFunction($node->name, $scope)) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction($node->name, $scope);
        $name = strtolower($function->getName());
        if (!$function->isBuiltin() || !\in_array($name, ['idn_to_ascii', 'idn_to_utf8'], true)) {
            return [];
        }

        $flagsName = $name === 'idn_to_ascii' ? 'IDNA_NONTRANSITIONAL_TO_ASCII' : 'IDNA_NONTRANSITIONAL_TO_UNICODE';
        $arguments = [];
        foreach ($node->args as $index => $argument) {
            if (!$argument instanceof Arg || $argument->unpack) {
                continue;
            }

            $argumentName = $argument->name?->toString();
            $position = $argumentName === null ? $index : (self::PARAMETER_POSITIONS[$argumentName] ?? null);
            if ($position === null) {
                continue;
            }

            $arguments[$position] = $argument->value;
        }

        if (!isset($arguments[1], $arguments[2])
            || !self::containsFlag($arguments[1], $flagsName, $scope)
            || !self::isConstant($arguments[2], 'INTL_IDNA_VARIANT_UTS46', $scope)) {
            return [RuleErrorBuilder::message(\sprintf('Pass %s and INTL_IDNA_VARIANT_UTS46 explicitly to %s().', $flagsName, $name))
                ->identifier('shopware.explicitIdnConversionOptions')
                ->tip(\sprintf('Use %s and INTL_IDNA_VARIANT_UTS46 as the flags and variant arguments.', $flagsName))
                ->build()];
        }

        return [];
    }

    private static function containsFlag(Node\Expr $expression, string $expected, Scope $scope): bool
    {
        if ($expression instanceof ConstFetch) {
            return self::isConstant($expression, $expected, $scope);
        }

        return $expression instanceof BitwiseOr
            && (self::containsFlag($expression->left, $expected, $scope) || self::containsFlag($expression->right, $expected, $scope));
    }

    private static function isConstant(Node\Expr $expression, string $expected, Scope $scope): bool
    {
        return $expression instanceof ConstFetch
            && strcasecmp(ltrim($scope->resolveName($expression->name), '\\'), $expected) === 0;
    }
}
