<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * Forbids an inline `@var StaticEntityRepository<...>` annotation on the construction of the stub in tests.
 * The generic is inferred from a search that carries the collection type; where no search does, the template is bound with
 * `StaticEntityRepository::of(FooCollection::class, ...)`. The annotations are noise for the reader and the reviewer and hide
 * which of the two situations the test is in. A `@var` on a property stays, that is the property's type declaration.
 *
 * @implements Rule<Expression>
 *
 * @internal
 */
#[Package('framework')]
class StaticEntityRepositoryVarAnnotationRule implements Rule
{
    private const VAR_TAG_PATTERN = '/@var\s+\\\\?(?:[\w\\\\]+\\\\)?StaticEntityRepository\s*<\s*\\\\?(?:[\w\\\\]+\\\\)?(\w+)/';

    private const TYPED_COLLECTION = 'typed';

    private const GENERIC_COLLECTION = 'generic';

    private const NO_COLLECTION = 'none';

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

        $construction = $this->staticRepositoryConstruction($node, $scope);
        if ($construction === null) {
            return [];
        }

        $docComment = $node->getDocComment()?->getText() ?? '';
        if (preg_match(self::VAR_TAG_PATTERN, $docComment, $match) !== 1) {
            return [];
        }

        $message = match ($this->collectionInTheSearches($construction, $scope)) {
            self::TYPED_COLLECTION => 'Drop the @var annotation on this StaticEntityRepository: a search carries the collection type, the generic is inferred from it.',
            self::GENERIC_COLLECTION => \sprintf(
                'Replace the generic EntityCollection in the searches of this StaticEntityRepository with %s, the collection the test expects, and drop the @var annotation.',
                $match[1]
            ),
            default => \sprintf(
                'Bind this StaticEntityRepository with StaticEntityRepository::of(%s::class, ...) instead of a @var annotation: no search carries the collection type.',
                $match[1]
            ),
        };

        return [
            RuleErrorBuilder::message($message)
                ->identifier('shopware.staticEntityRepositoryVarAnnotation')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function staticRepositoryConstruction(Expression $node, Scope $scope): ?New_
    {
        if (!$node->expr instanceof Assign || !$node->expr->expr instanceof New_) {
            return null;
        }

        $new = $node->expr->expr;
        if (!$new->class instanceof Name || $scope->resolveName($new->class) !== StaticEntityRepository::class) {
            return null;
        }

        return $new;
    }

    /**
     * What the searches passed to the constructor bind the generic to: a concrete collection or a search result, only the
     * generic EntityCollection, or nothing
     */
    private function collectionInTheSearches(New_ $construction, Scope $scope): string
    {
        $searches = $construction->getArgs()[0]->value ?? null;
        if ($searches === null) {
            return self::NO_COLLECTION;
        }

        $types = [];
        if ($searches instanceof Array_) {
            foreach ($searches->items as $item) {
                if ($item !== null) {
                    $types[] = $scope->getType($item->value);
                }
            }
        } else {
            // the whole searches argument is a variable: look at its elements
            $type = $scope->getType($searches);
            foreach ($type->getConstantArrays() as $constantArray) {
                array_push($types, ...$constantArray->getValueTypes());
            }
            $types[] = $type->getIterableValueType();
        }

        $collection = new ObjectType(EntityCollection::class);
        $searchResult = new ObjectType(EntitySearchResult::class);
        $found = self::NO_COLLECTION;
        foreach ($types as $type) {
            if ($searchResult->isSuperTypeOf($type)->yes()) {
                return self::TYPED_COLLECTION;
            }
            if (!$collection->isSuperTypeOf($type)->yes()) {
                continue;
            }
            if ($type->getObjectClassNames() !== [EntityCollection::class]) {
                return self::TYPED_COLLECTION;
            }
            $found = self::GENERIC_COLLECTION;
        }

        return $found;
    }
}
