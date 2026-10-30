<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\FileTypeMapper;
use Shopware\Core\Framework\Log\Package;

/**
 * No inline `@var Foo $foo` on an assignment that a later `assertInstanceOf(Foo::class, $foo)` narrows anyway, before any other
 * use of `$foo`: the assertion already gives PHPStan the type, the annotation only repeats it.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NoVarAnnotationBeforeAssertInstanceOfRule implements Rule
{
    public function __construct(private readonly FileTypeMapper $fileTypeMapper)
    {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();
        if (!TestRuleHelper::isTestClass($classReflection)) {
            return [];
        }

        $errors = [];
        $finder = new NodeFinder();
        foreach ($node->getOriginalNode()->getMethods() as $method) {
            $blocks = $finder->find([$method], static fn (Node $candidate): bool => property_exists($candidate, 'stmts') && \is_array($candidate->stmts));
            foreach ($blocks as $block) {
                \assert(property_exists($block, 'stmts') && \is_array($block->stmts));
                $errors = [...$errors, ...$this->processStatements(array_values($block->stmts), $method, $scope, $classReflection->getName())];
            }
        }

        return $errors;
    }

    /**
     * @param list<Node> $stmts
     *
     * @return list<RuleError>
     */
    private function processStatements(array $stmts, ClassMethod $method, Scope $scope, string $className): array
    {
        $errors = [];
        foreach ($stmts as $i => $stmt) {
            if (!$stmt instanceof Expression || !$stmt->expr instanceof Assign || !$stmt->expr->var instanceof Variable || !\is_string($stmt->expr->var->name)) {
                continue;
            }

            $variable = $stmt->expr->var->name;
            $annotated = $this->annotatedClass($stmt, $variable, $method, $scope, $className);
            if ($annotated === null) {
                continue;
            }

            foreach (\array_slice($stmts, $i + 1) as $next) {
                if ($this->assertsInstanceOf($next, $variable, $annotated, $scope)) {
                    $errors[] = RuleErrorBuilder::message(\sprintf('Drop the @var annotation: the assertInstanceOf() below already narrows $%s to %s.', $variable, basename(str_replace('\\', '/', $annotated))))
                        ->identifier('shopware.varAnnotationBeforeAssertInstanceOf')
                        ->line($stmt->getStartLine())
                        ->build();

                    break;
                }

                if ($this->usesVariable($next, $variable)) {
                    break;
                }
            }
        }

        return $errors;
    }

    private function annotatedClass(Expression $stmt, string $variable, ClassMethod $method, Scope $scope, string $className): ?string
    {
        $docComment = $stmt->getDocComment()?->getText();
        if ($docComment === null || !str_contains($docComment, '@var')) {
            return null;
        }

        $varTags = $this->fileTypeMapper->getResolvedPhpDoc($scope->getFile(), $className, null, $method->name->toString(), $docComment)->getVarTags();
        foreach ($varTags as $name => $varTag) {
            if (\is_int($name) || $name === $variable) {
                $classes = $varTag->getType()->getObjectClassNames();

                return \count($classes) === 1 && $varTag->getType()->isObject()->yes() ? $classes[0] : null;
            }
        }

        return null;
    }

    private function assertsInstanceOf(Node $stmt, string $variable, string $class, Scope $scope): bool
    {
        if (!$stmt instanceof Expression || (!$stmt->expr instanceof StaticCall && !$stmt->expr instanceof MethodCall)) {
            return false;
        }

        $call = $stmt->expr;
        if (!$call->name instanceof Identifier || $call->name->toLowerString() !== 'assertinstanceof') {
            return false;
        }

        $expected = $call->getArgs()[0]->value ?? null;
        $actual = $call->getArgs()[1]->value ?? null;

        return $expected instanceof ClassConstFetch
            && $expected->class instanceof Name
            && $expected->name instanceof Identifier
            && $expected->name->toLowerString() === 'class'
            && strcasecmp($scope->resolveName($expected->class), $class) === 0
            && $actual instanceof Variable
            && $actual->name === $variable;
    }

    private function usesVariable(Node $stmt, string $variable): bool
    {
        return (new NodeFinder())->findFirst([$stmt], static fn (Node $node): bool => $node instanceof Variable && $node->name === $variable) !== null;
    }
}
