<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Node\TypesNode;
use Twig\Source;
use Twig\Token;
use Twig\TokenStream;
use TwigStan\Twig\Node\NodeFinder;

/**
 * @internal
 *
 * @phpstan-type Contract array<string, array{type: Type, optional: bool}>
 */
#[Package('framework')]
final class TemplateContracts implements DynamicStaticMethodReturnTypeExtension
{
    /**
     * @var array<string, Contract|null>
     */
    private array $contracts = [];

    /**
     * @var array<string, string>
     */
    private array $paths = [];

    public function __construct(private readonly Environment $twig, private readonly TypeStringResolver $types)
    {
    }

    /**
     * Emitted only by the analysis visitor; preserve the original include context if this code is rendered.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    public static function context(Environment $env, array $context, array $variables, bool $withContext, string $template, string $type, bool $project = true): array
    {
        return $withContext ? array_merge($context, $variables) : $variables;
    }

    public function getClass(): string
    {
        return self::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'context';
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): ?Type
    {
        $args = $methodCall->getArgs();
        if (isset($args[6]) && $scope->getType($args[6]->value)->isFalse()->yes()) {
            return $scope->getType(new Ternary(
                $args[3]->value,
                new FuncCall(new FullyQualified('array_merge'), [$args[1], $args[2]]),
                $args[2]->value,
            ));
        }
        $types = isset($args[5]) ? $scope->getType($args[5]->value)->getConstantStrings() : [];

        return \count($types) === 1 ? $this->types->resolve($types[0]->getValue()) : null;
    }

    public function project(string $template, ?DependencyTracker $dependencies = null): ?Type
    {
        $contract = $this->get($template);
        if (isset($this->paths[$template])) {
            $dependencies?->trackFileDependency($this->paths[$template]);
        }
        if ($contract === null) {
            return null;
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();
        foreach ($contract as $name => $field) {
            $builder->setOffsetValueType(new ConstantStringType($name), $field['type'], $field['optional']);
        }

        return $builder->getArray();
    }

    /**
     * @param list<Type> $sources Context sources in descending override priority.
     *
     * @return list<IdentifierRuleError>
     */
    public function validate(string $template, array $sources): array
    {
        $errors = [];
        foreach ($this->get($template) ?? [] as $name => $field) {
            $key = new ConstantStringType($name);
            $presence = TrinaryLogic::createNo();
            $mismatch = null;
            foreach ($sources as $source) {
                $hasKey = $source->hasOffsetValueType($key);
                if ($hasKey->no()) {
                    continue;
                }

                $inferred = $source->getOffsetValueType($key);
                if (!$inferred instanceof MixedType && !$field['type']->isSuperTypeOf($inferred)->yes()) {
                    $mismatch ??= RuleErrorBuilder::message(\sprintf('Template "%s" expects "%s" to be %s, %s given.', $template, $name, $field['type']->describe(VerbosityLevel::precise()), $inferred->describe(VerbosityLevel::precise())))
                        ->identifier('twigstan.templateContractMismatch')->build();
                }

                $presence = $presence->or($hasKey);
                if ($hasKey->yes()) {
                    break;
                }
            }

            if (!$field['optional'] && !$presence->yes()) {
                $errors[] = RuleErrorBuilder::message(\sprintf('Template "%s" requires variable "%s" to be defined.', $template, $name))
                    ->identifier('twigstan.templateContractMissing')->build();
            } elseif ($mismatch !== null) {
                $errors[] = $mismatch;
            }
        }

        return $errors;
    }

    public function getRequestGlobals(): Type
    {
        $builder = ConstantArrayTypeBuilder::createEmpty();
        foreach (StorefrontRenderContextCollector::getGlobalTypes() as $name => $type) {
            $builder->setOffsetValueType(new ConstantStringType($name), $type, optional: true);
        }

        return $builder->getArray();
    }

    /**
     * @return array{fields: array<string, array{type: string, optional: bool}>, project: bool}|null
     */
    public static function declaration(Environment $twig, Source $source): ?array
    {
        if (!str_contains($source->getCode(), 'template-contract') && !str_contains($source->getCode(), 'external-interface')) {
            return null;
        }

        $stream = $twig->tokenize($source);
        $contract = null;
        $project = true;
        while (!$stream->isEOF()) {
            $token = $stream->next();
            if (!$token->test(Token::BLOCK_START_TYPE) || preg_match('/^\h*(template-contract|external-interface)\h*$/m', $token->getDocumentation() ?? '', $marker) !== 1) {
                continue;
            }

            if (!$stream->test(Token::NAME_TYPE, 'types') || $contract !== null) {
                throw new SyntaxError(\sprintf('The %s marker must precede a single types declaration.', $marker[1]), $token->getLine(), $source);
            }

            $tokens = [$token];
            do {
                $part = $stream->next();
                $tokens[] = $part;
            } while (!$part->test(Token::BLOCK_END_TYPE));
            $tokens[] = new Token(Token::EOF_TYPE, '', $token->getLine());
            $module = (new Environment(new ArrayLoader()))->parse(new TokenStream($tokens, $source));
            $contract = [];
            $project = $marker[1] === 'template-contract';
            foreach ((new NodeFinder())->findInstanceOf($module, TypesNode::class) as $node) {
                foreach ($node->getAttribute('mapping') as $name => $field) {
                    $contract[$name] = $field;
                }
            }
        }

        return $contract === null ? null : ['fields' => $contract, 'project' => $project];
    }

    /**
     * @return Contract|null
     */
    private function get(string $template): ?array
    {
        if (\array_key_exists($template, $this->contracts)) {
            return $this->contracts[$template];
        }

        try {
            $source = $this->twig->getLoader()->getSourceContext($template);
        } catch (LoaderError) {
            return $this->contracts[$template] = null;
        }

        if ($source->getPath() !== '') {
            $this->paths[$template] = $source->getPath();
        }

        $declaration = self::declaration($this->twig, $source);
        if ($declaration === null) {
            return $this->contracts[$template] = null;
        }
        $contract = [];
        foreach ($declaration['fields'] as $name => $field) {
            $contract[$name] = ['type' => $this->types->resolve($field['type']), 'optional' => $field['optional']];
        }

        return $this->contracts[$template] = $contract;
    }
}
