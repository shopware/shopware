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
 * Forbids an inline `@var StaticEntityRepository<...>` annotation on the construction of the stub in tests.
 * The generic is inferred from a search that carries the collection type; where no search does, the template is bound with
 * `StaticEntityRepository::of(FooCollection::class, ...)`. The annotations are noise for the reader and the reviewer and hide
 * which of the two situations the test is in. A `@var` on a property stays, that is the property's type declaration.
 * An annotation with `EntityCollection` itself as collection, with or without its entity type, is left alone as well:
 * `::of()` cannot express it.
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

        if (!$node->expr instanceof Assign || !$node->expr->expr instanceof New_) {
            return [];
        }

        $construction = $node->expr->expr;
        if (!$construction->class instanceof Name || $scope->resolveName($construction->class) !== StaticEntityRepository::class) {
            return [];
        }

        $annotatedType = $this->annotatedType($node, $scope);
        if ($annotatedType === null) {
            return [];
        }

        $collection = $annotatedType->getTemplateType(StaticEntityRepository::class, 'TEntityCollection');
        $collectionClasses = $collection->getObjectClassNames();
        if ($collectionClasses === [] || $collectionClasses === [EntityCollection::class]) {
            return [];
        }

        if ($this->searchesCarryACollection($construction, $scope)) {
            $message = 'Drop the @var annotation on this StaticEntityRepository: a search carries the collection type, the generic is inferred from it.';
        } else {
            $message = \sprintf(
                'Bind this StaticEntityRepository with StaticEntityRepository::of(%s::class, ...) instead of a @var annotation: no search carries the collection type.',
                basename(str_replace('\\', '/', $collectionClasses[0]))
            );
        }

        return [
            RuleErrorBuilder::message($message)
                ->identifier('shopware.staticEntityRepositoryVarAnnotation')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * The type of the `@var` tag on the statement for the assigned variable, if it names the stub
     */
    private function annotatedType(Expression $node, Scope $scope): ?Type
    {
        $docComment = $node->getDocComment();
        if ($docComment === null || !str_contains($docComment->getText(), 'StaticEntityRepository')) {
            return null;
        }

        $resolved = $this->fileTypeMapper->getResolvedPhpDoc(
            $scope->getFile(),
            $scope->getClassReflection()?->getName(),
            $scope->getTraitReflection()?->getName(),
            $scope->getFunctionName(),
            $docComment->getText()
        );

        \assert($node->expr instanceof Assign);
        $variable = $node->expr->var instanceof Variable && \is_string($node->expr->var->name) ? $node->expr->var->name : null;

        foreach ($resolved->getVarTags() as $name => $varTag) {
            if (\is_int($name) || $name === $variable) {
                $type = $varTag->getType();

                return \in_array(StaticEntityRepository::class, $type->getObjectClassNames(), true) ? $type : null;
            }
        }

        return null;
    }

    /**
     * What the constructor would infer without the annotation: a concrete collection passed as a search, or carried by a
     * search result. Empty searches, id lists and the generic EntityCollection bind nothing.
     * The annotated statement is analysed with its annotation applied, so the construction's own type cannot be asked.
     */
    private function searchesCarryACollection(New_ $construction, Scope $scope): bool
    {
        $searches = $construction->getArgs()[0]->value ?? null;
        if ($searches === null) {
            return false;
        }

        $types = [];
        if ($searches instanceof Array_) {
            foreach ($searches->items as $item) {
                if ($item !== null) {
                    $types[] = $scope->getType($item->value);
                }
            }
        } else {
            $type = $scope->getType($searches);
            foreach ($type->getConstantArrays() as $constantArray) {
                array_push($types, ...$constantArray->getValueTypes());
            }
            $types[] = $type->getIterableValueType();
        }

        $collection = new ObjectType(EntityCollection::class);
        $searchResult = new ObjectType(EntitySearchResult::class);
        foreach ($types as $type) {
            if ($searchResult->isSuperTypeOf($type)->yes()) {
                $type = $type->getTemplateType(EntitySearchResult::class, 'TEntityCollection');
            } elseif (!$collection->isSuperTypeOf($type)->yes()) {
                continue;
            }

            $classes = $type->getObjectClassNames();
            if ($classes !== [] && $classes !== [EntityCollection::class]) {
                return true;
            }
        }

        return false;
    }
}
