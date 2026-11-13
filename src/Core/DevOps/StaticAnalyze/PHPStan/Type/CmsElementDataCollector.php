<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-import-type Manifest from CmsElementDataPreparation
 *
 * @phpstan-type Entry array{callback: string, parameter: string, kind: string, value: string, argument: string}
 *
 * @implements Collector<Node, list<Entry>>
 *
 * @internal
 */
#[Package('framework')]
class CmsElementDataCollector implements Collector
{
    /**
     * @var Manifest
     */
    private array $manifest;

    public function __construct(private readonly string $manifestFile)
    {
        $this->manifest = json_decode((new Filesystem())->readFile($manifestFile), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @param Scope&DependencyTracker $scope
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        $scope->trackFileDependency($this->manifestFile);
        $callback = $scope->getClassReflection()?->getName() . '::' . ($node instanceof Node\Stmt\ClassMethod ? $node->name->name : $scope->getFunctionName());
        $method = $this->manifest['methods'][$callback] ?? null;
        if ($method === null || $scope->isInAnonymousFunction()) {
            return null;
        }
        $entry = ['callback' => $callback, 'parameter' => '', 'kind' => 'unresolved', 'value' => '', 'argument' => ''];
        if ($node instanceof Node\Stmt\Return_ && str_ends_with($callback, '::getType') && $node->expr !== null) {
            $type = $scope->getType($node->expr);
            $strings = $type->getConstantStrings();
            if ($strings === [] || !TypeCombinator::union(...$strings)->equals($type)) {
                return [$entry];
            }

            return array_map(static fn ($string): array => [...$entry, 'kind' => 'name', 'value' => $string->getValue()], $strings);
        }
        $entries = [];
        foreach ($method['slots'] as $parameter) {
            $write = [...$entry, 'parameter' => $parameter];
            if ($node instanceof Node\Stmt\ClassMethod && (new NodeFinder())->findFirst($node->stmts ?? [], static fn (Node $child): bool => $child instanceof Node\Expr\Closure || $child instanceof Node\Expr\ArrowFunction || ($child instanceof Node\Expr\CallLike && $child->isFirstClassCallable())) !== null) {
                $entries[] = $write;
            }
            if (($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignRef) && $node->expr instanceof Node\Expr\Variable && $node->expr->name === $parameter) {
                $entries[] = $write;
            }
            if (!$node instanceof Node\Expr\CallLike) {
                continue;
            }
            if ($node->isFirstClassCallable()) {
                $entries[] = $write;
                continue;
            }
            if ($node instanceof Node\Expr\MethodCall && $node->var instanceof Node\Expr\Variable && $node->var->name === $parameter) {
                if (!$node->name instanceof Node\Identifier) {
                    $entries[] = $write;
                } elseif ($node->name->name === 'setData') {
                    $argument = $node->getArgs()[0] ?? null;
                    $type = $argument !== null ? $scope->getType($argument->value) : null;
                    $entries[] = $type !== null && (new ObjectType(Struct::class))->isSuperTypeOf($type)->yes()
                        ? [...$write, 'kind' => 'data', 'value' => (new Printer())->print($type->toPhpDocNode())]
                        : $write;
                } elseif (!preg_match('/^(get|has|is|set)/', $node->name->name)) {
                    $entries[] = $write;
                }
            }
            foreach ($node->getArgs() as $index => $argument) {
                if (!$argument->value instanceof Node\Expr\Variable || $argument->value->name !== $parameter) {
                    if (($argument->value instanceof Node\Expr\Array_ || $argument->value instanceof Node\Expr\Ternary) && (new NodeFinder())->findFirst([$argument->value], static fn (Node $child): bool => $child instanceof Node\Expr\Variable && $child->name === $parameter) !== null) {
                        $entries[] = $write;
                    }
                    continue;
                }
                // shortcut: external helper dispatch stays broad; resolve it when processor data types are needed.
                $target = '';
                if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier && $node->var instanceof Node\Expr\Variable && $node->var->name === 'this') {
                    $target = '$this::' . $node->name->name;
                } elseif ($node instanceof Node\Expr\StaticCall && $node->name instanceof Node\Identifier && $node->class instanceof Node\Name && $node->class->toLowerString() === 'parent') {
                    $parent = $scope->getClassReflection()?->getParentClass();
                    if ($parent !== null && $parent->hasNativeMethod($node->name->name)) {
                        $target = $parent->getNativeMethod($node->name->name)->getDeclaringClass()->getName() . '::' . $node->name->name;
                    }
                }
                $entries[] = $target !== '' && !$argument->unpack
                    ? [...$write, 'kind' => 'call', 'value' => $target, 'argument' => $argument->name->name ?? (string) $index]
                    : $write;
            }
        }

        return $entries !== [] ? $entries : null;
    }
}
