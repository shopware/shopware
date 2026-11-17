<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\VerbosityLevel;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<FuncCall>
 */
#[Package('framework')]
final class TypeHintCompatibilityRule implements Rule
{
    public function __construct(private readonly TypeStringResolver $typeResolver)
    {
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Name || $node->name->toLowerString() !== 'twigstan_type_hint') {
            return [];
        }

        $arguments = $node->getArgs();
        if (!isset($arguments[3]) || !$arguments[1]->value instanceof String_ || !$arguments[2]->value instanceof String_) {
            return [];
        }

        $name = $arguments[1]->value->value;
        $key = new ConstantStringType($name);
        $context = $scope->getType($arguments[0]->value);
        $presence = $context->hasOffsetValueType($key);
        if (!$presence->yes() && !$scope->getType($arguments[3]->value)->isTrue()->yes()) {
            return [RuleErrorBuilder::message(\sprintf('Type declaration requires variable "%s" to be defined.', $name))
                ->identifier('twigstan.typeHintMissing')
                ->build()];
        }

        if ($presence->no()) {
            return [];
        }

        $inferred = $context->getOffsetValueType($key);
        $declared = $this->typeResolver->resolve($arguments[2]->value->value);
        if ($inferred instanceof MixedType || $declared->isSuperTypeOf($inferred)->yes()) {
            return [];
        }

        return [RuleErrorBuilder::message(\sprintf(
            'Type declaration for "%s" expects %s, but inferred type is %s.',
            $name,
            $declared->describe(VerbosityLevel::precise()),
            $inferred->describe(VerbosityLevel::precise()),
        ))
            ->identifier('twigstan.typeHintMismatch')
            ->build()];
    }
}
