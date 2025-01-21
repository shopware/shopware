<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Checks if class methods are typed natively.
 *
 * @internal
 *
 * @implements Rule<ClassMethod>
 */
#[Package('framework')]
class MethodNativeTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Ensure we're in a class method context
        if (!$scope->isInClass()) {
            return [];
        }

        $methodName = (string) $node->name;
        if ($methodName === '__construct') {
            return [];
        }

        $docComment = $node->getDocComment();
        if ($docComment instanceof Doc) {
            /**
             * Will be natively typed with the next major version
             *
             * @deprecated tag:v6.7.0 Remove this if condition, as from then on, every property should have a type
             */
            if (str_contains($docComment->getText(), '@deprecated tag:v6.7.0 - reason:return-type-change')) {
                return [];
            }
        }

        // Get the method's return type
        if ($node->returnType === null) {
            return [
                RuleErrorBuilder::message(\sprintf('Native return type for method "%s" is missing', $methodName))
                    ->identifier('shopware.methodReturnNativeType')
                    ->build(),
            ];
        }

        return [];
    }
}
