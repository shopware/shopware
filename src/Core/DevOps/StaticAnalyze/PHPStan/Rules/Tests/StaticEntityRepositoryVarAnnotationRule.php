<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * No `@var StaticEntityRepository<...>` on the construction of the stub: the generic is inferred from a search that carries
 * the collection, otherwise bound with `StaticEntityRepository::of()`. Properties and `EntityCollection` annotations are left alone.
 *
 * @implements Rule<Expression>
 *
 * @internal
 */
#[Package('framework')]
class StaticEntityRepositoryVarAnnotationRule implements Rule
{
    public function __construct(private readonly FileTypeMapper $fileTypeMapper)
    {
    }

    public function getNodeType(): string
    {
        return Expression::class;
    }

    /**
     * @param Expression $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || !TestRuleHelper::isTestClass($classReflection)) {
            return [];
        }

        $assign = $node->expr;
        if (!$assign instanceof Assign || !$assign->expr instanceof New_) {
            return [];
        }

        $construction = $assign->expr;
        $class = $construction->class;
        if (!$class instanceof Name || $scope->resolveName($class) !== StaticEntityRepository::class) {
            return [];
        }

        $variable = $assign->var instanceof Variable && \is_string($assign->var->name) ? $assign->var->name : null;
        $collection = $this->annotatedType($node, $scope, $variable)?->getTemplateType(StaticEntityRepository::class, 'TEntityCollection');
        $classes = $collection?->getObjectClassNames() ?? [];
        if ($classes === [] || $classes === [EntityCollection::class]) {
            return [];
        }

        $message = $this->searchesCarryACollection($construction, $scope)
            ? 'Drop the @var annotation: a search carries the collection type, the generic is inferred from it.'
            : \sprintf('Use StaticEntityRepository::of(%s::class, ...) instead of the @var annotation: no search carries the collection type.', basename(str_replace('\\', '/', $classes[0])));

        return [RuleErrorBuilder::message($message)->identifier('shopware.staticEntityRepositoryVarAnnotation')->line($node->getStartLine())->build()];
    }

    private function annotatedType(Expression $node, Scope $scope, ?string $variable): ?Type
    {
        $docComment = $node->getDocComment()?->getText();
        if ($docComment === null || !str_contains($docComment, 'StaticEntityRepository')) {
            return null;
        }

        $varTags = $this->fileTypeMapper->getResolvedPhpDoc($scope->getFile(), $scope->getClassReflection()?->getName(), $scope->getTraitReflection()?->getName(), $scope->getFunctionName(), $docComment)->getVarTags();
        foreach ($varTags as $name => $varTag) {
            if (\is_int($name) || $name === $variable) {
                return $varTag->getType();
            }
        }

        return null;
    }

    /**
     * A concrete collection passed as a search, or inside a search result, binds the generic; empty searches, id lists and the
     * generic EntityCollection do not. The statement's own type cannot be asked, it already carries the annotation.
     */
    private function searchesCarryACollection(New_ $construction, Scope $scope): bool
    {
        $searches = $construction->getArgs()[0]->value ?? null;
        if ($searches === null) {
            return false;
        }

        $types = $searches instanceof Array_
            ? array_map(static fn ($item) => $scope->getType($item->value), array_filter($searches->items))
            : [$scope->getType($searches)->getIterableValueType()];

        foreach ($types as $type) {
            if ((new ObjectType(EntitySearchResult::class))->isSuperTypeOf($type)->yes()) {
                $type = $type->getTemplateType(EntitySearchResult::class, 'TEntityCollection');
            } elseif (!(new ObjectType(EntityCollection::class))->isSuperTypeOf($type)->yes()) {
                continue;
            }

            if ($type->getObjectClassNames() !== [] && $type->getObjectClassNames() !== [EntityCollection::class]) {
                return true;
            }
        }

        return false;
    }
}
