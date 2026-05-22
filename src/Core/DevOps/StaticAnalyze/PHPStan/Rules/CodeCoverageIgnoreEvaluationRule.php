<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<Class_>
 */
#[Package('framework')]
class CodeCoverageIgnoreEvaluationRule implements Rule
{
    /**
     * What "logic" means for this rule: anything that introduces a branch or an
     * error path. Calls, instantiation, arithmetic, and coalesce are intentionally
     * absent — they're not branching by themselves, and the called code has its
     * own coverage story.
     *
     * @var list<class-string<Node>>
     */
    private const LOGIC_NODE_TYPES = [
        Stmt\If_::class,
        Stmt\ElseIf_::class,
        Stmt\Else_::class,
        Stmt\Switch_::class,
        Expr\Match_::class,
        Stmt\While_::class,
        Stmt\Do_::class,
        Stmt\For_::class,
        Stmt\Foreach_::class,
        Stmt\TryCatch::class,
        Stmt\Catch_::class,
        Stmt\Throw_::class,
        Expr\Throw_::class,
        Expr\Ternary::class,
    ];

    private readonly Parser $parser;

    private readonly NodeFinder $finder;

    /**
     * @var array<string, list<ClassMethod>>
     */
    private array $traitMethodCache = [];

    /**
     * file path => alias => FQCN
     *
     * @var array<string, array<string, string>>
     */
    private array $useMapCache = [];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
        $this->parser = (new ParserFactory())->createForHostVersion();
        $this->finder = new NodeFinder();
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classHasIgnore = $this->docHasCodeCoverageIgnore($node);
        $className = $this->className($node);

        if ($classHasIgnore && $this->isThrowable($node, $className)) {
            return [
                RuleErrorBuilder::message(\sprintf(
                    'Class %s extends \\Throwable and must not carry @codeCoverageIgnore — exception classes are already excluded from coverage. Remove the annotation.',
                    $className,
                ))
                    ->identifier('shopware.codeCoverageIgnoreOnException')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        $classExempted = $classHasIgnore && $this->hasSeeIntegrationTest($node, $scope);

        $errors = [];

        foreach ($node->getMethods() as $method) {
            $methodName = (string) $method->name;

            if ($classHasIgnore && !$classExempted && $this->methodContainsLogic($method)) {
                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'Class %s is annotated @codeCoverageIgnore but method %s() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                    $className,
                    $methodName,
                ))
                    ->identifier('shopware.codeCoverageIgnoreOnLogic')
                    ->line($method->getStartLine())
                    ->build();

                continue;
            }

            if (!$this->docHasCodeCoverageIgnore($method)) {
                continue;
            }

            if ($this->hasSeeIntegrationTest($method, $scope)) {
                continue;
            }

            if ($this->methodContainsLogic($method)) {
                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'Method %s::%s() is annotated @codeCoverageIgnore but contains logic. Remove the annotation, extract the logic to a covered method, or add a @see pointing to an existing integration test that exercises it.',
                    $className,
                    $methodName,
                ))
                    ->identifier('shopware.codeCoverageIgnoreOnLogic')
                    ->line($method->getStartLine())
                    ->build();
            }
        }

        if ($classHasIgnore && !$classExempted) {
            foreach ($this->traitMethods($node) as [$traitName, $method]) {
                if (!$this->methodContainsLogic($method)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'Class %s is annotated @codeCoverageIgnore but inherited trait method %s::%s() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                    $className,
                    $traitName,
                    (string) $method->name,
                ))
                    ->identifier('shopware.codeCoverageIgnoreOnLogic')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    private function isThrowable(Class_ $node, string $className): bool
    {
        if ($node->extends === null) {
            return false;
        }

        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)
            ->getNativeReflection()
            ->isSubclassOf(\Throwable::class);
    }

    private function hasSeeIntegrationTest(Node $node, Scope $scope): bool
    {
        $doc = $node->getDocComment();
        if ($doc === null) {
            return false;
        }

        if (!preg_match_all('/@see\s+(\S+)/', $doc->getText(), $matches)) {
            return false;
        }

        $useMap = null;

        foreach ($matches[1] as $reference) {
            $rawClass = explode('::', $reference)[0];
            $candidate = ltrim($rawClass, '\\');
            if ($candidate === '') {
                continue;
            }

            $resolved = $candidate;

            // Unqualified (no `\`) references are resolved against the file's
            // use statements. Qualified refs (with `\` or relative path) are
            // taken as-is, matching common phpdoc conventions in this codebase.
            if (!str_starts_with($rawClass, '\\') && !str_contains($candidate, '\\')) {
                $useMap ??= $this->getUseMap($scope->getFile());
                $resolved = $useMap[$candidate] ?? $candidate;
            }

            if (!str_contains($resolved, '\\Tests\\Integration\\')) {
                continue;
            }

            if ($this->reflectionProvider->hasClass($resolved)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function getUseMap(string $file): array
    {
        if (\array_key_exists($file, $this->useMapCache)) {
            return $this->useMapCache[$file];
        }

        $this->useMapCache[$file] = [];

        if (!is_file($file)) {
            return [];
        }

        $source = @file_get_contents($file);
        if ($source === false) {
            return [];
        }

        try {
            $stmts = $this->parser->parse($source);
        } catch (\Throwable) {
            return [];
        }

        if ($stmts === null) {
            return [];
        }

        $map = [];
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\Namespace_) {
                foreach ($stmt->stmts as $inner) {
                    $this->collectUses($inner, $map);
                }
            } else {
                $this->collectUses($stmt, $map);
            }
        }

        return $this->useMapCache[$file] = $map;
    }

    /**
     * @param array<string, string> $map
     */
    private function collectUses(Node $stmt, array &$map): void
    {
        if ($stmt instanceof Stmt\Use_) {
            foreach ($stmt->uses as $use) {
                $map[$use->getAlias()->name] = $use->name->toString();
            }

            return;
        }

        if ($stmt instanceof Stmt\GroupUse) {
            $prefix = $stmt->prefix->toString();
            foreach ($stmt->uses as $use) {
                $map[$use->getAlias()->name] = $prefix . '\\' . $use->name->toString();
            }
        }
    }

    private function className(Class_ $node): string
    {
        if ($node->namespacedName !== null) {
            return $node->namespacedName->toString();
        }

        return $node->name?->name ?? '<anonymous class>';
    }

    private function docHasCodeCoverageIgnore(Node $node): bool
    {
        $doc = $node->getDocComment();
        if ($doc === null) {
            return false;
        }

        return (bool) preg_match('/@codeCoverageIgnore(?![A-Za-z])/', $doc->getText());
    }

    private function methodContainsLogic(ClassMethod $method): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        foreach ($method->stmts as $stmt) {
            if ($this->nodeContainsLogic($stmt)) {
                return true;
            }
        }

        return false;
    }

    private function nodeContainsLogic(Node $node): bool
    {
        foreach (self::LOGIC_NODE_TYPES as $type) {
            if ($node instanceof $type) {
                return true;
            }
        }

        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->{$name};
            if ($value instanceof Node) {
                if ($this->nodeContainsLogic($value)) {
                    return true;
                }
            } elseif (\is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Node && $this->nodeContainsLogic($item)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return iterable<array{0: string, 1: ClassMethod}>
     */
    private function traitMethods(Class_ $class): iterable
    {
        foreach ($class->getTraitUses() as $use) {
            foreach ($use->traits as $traitName) {
                $name = $traitName->toString();
                foreach ($this->loadTraitMethods($name) as $method) {
                    yield [$name, $method];
                }
            }
        }
    }

    /**
     * @return list<ClassMethod>
     */
    private function loadTraitMethods(string $traitName): array
    {
        if (\array_key_exists($traitName, $this->traitMethodCache)) {
            return $this->traitMethodCache[$traitName];
        }

        $this->traitMethodCache[$traitName] = [];

        if (!$this->reflectionProvider->hasClass($traitName)) {
            return [];
        }

        $reflection = $this->reflectionProvider->getClass($traitName);
        if (!$reflection->isTrait()) {
            return [];
        }

        $file = $reflection->getFileName();
        if ($file === null || !is_file($file)) {
            return [];
        }

        $source = @file_get_contents($file);
        if ($source === false) {
            return [];
        }

        try {
            $stmts = $this->parser->parse($source);
        } catch (\Throwable) {
            return [];
        }

        if ($stmts === null) {
            return [];
        }

        $shortName = ($pos = strrpos($traitName, '\\')) === false ? $traitName : substr($traitName, $pos + 1);

        /** @var list<Trait_> $traitNodes */
        $traitNodes = $this->finder->findInstanceOf($stmts, Trait_::class);
        foreach ($traitNodes as $traitNode) {
            if ($traitNode->name === null || $traitNode->name->name !== $shortName) {
                continue;
            }

            $this->traitMethodCache[$traitName] = $traitNode->getMethods();

            return $this->traitMethodCache[$traitName];
        }

        return [];
    }
}
