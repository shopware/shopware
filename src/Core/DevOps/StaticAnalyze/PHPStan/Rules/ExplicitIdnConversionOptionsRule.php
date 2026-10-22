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
            || !self::containsConstant($arguments[1], $flagsName)
            || !self::isConstant($arguments[2], 'INTL_IDNA_VARIANT_UTS46')) {
            return [RuleErrorBuilder::message(\sprintf('Pass %s and INTL_IDNA_VARIANT_UTS46 explicitly to %s().', $flagsName, $name))
                ->identifier('shopware.explicitIdnConversionOptions')
                ->build()];
        }

        return [];
    }

    private static function containsConstant(Node\Expr $expression, string $expected): bool
    {
        if ($expression instanceof ConstFetch) {
            return self::isConstant($expression, $expected);
        }

        return $expression instanceof BitwiseOr
            && (self::containsConstant($expression->left, $expected) || self::containsConstant($expression->right, $expected));
    }

    private static function isConstant(Node\Expr $expression, string $expected): bool
    {
        return $expression instanceof ConstFetch && strcasecmp(ltrim($expression->name->toString(), '\\'), $expected) === 0;
    }
}
