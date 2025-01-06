<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Checks if class properties are typed natively.
 * Promoted constructor properties are also considered
 *
 * @internal
 *
 * @implements Rule<Class_>
 */
#[Package('core')]
class PropertyNativeTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        $errors = [];

        // Check "normal" class properties
        foreach ($node->getProperties() as $property) {
            // Has a type, everything fine
            if ($property->type !== null) {
                continue;
            }

            $docComment = $property->getDocComment();
            if ($docComment instanceof Doc) {
                /**
                 * Will be natively typed with the next major version
                 *
                 * @deprecated tag:v6.7.0 Remove this if condition, as from then on, every property should have a type
                 */
                if (str_contains($docComment->getText(), '@deprecated tag:v6.7.0')) {
                    continue;
                }

                // resource and callable cannot be typed natively
                if (str_contains($docComment->getText(), '@var resource')
                    || str_contains($docComment->getText(), '@var callable')
                ) {
                    continue;
                }
            }

            $errors[] = RuleErrorBuilder::message(\sprintf('Native type for property "%s" is missing', $property->props[0]->name->name))
                ->identifier('shopware.propertyNativeType')
                ->line($property->getLine())
                ->build();
        }

        // Check promoted class properties, which are basically parameters of the constructor
        $constructor = $node->getMethod('__construct');
        if (!$constructor instanceof ClassMethod) {
            return $errors;
        }

        foreach ($constructor->getParams() as $param) {
            // If a constructor parameter is promoted it has a flag from 1-4
            if ($param->flags === 0) {
                continue;
            }

            // Has a type, everything fine
            if ($param->type !== null) {
                continue;
            }

            /**
             * Will be natively typed with the next major version
             *
             * @deprecated tag:v6.7.0 Remove this if condition, as from then on, every property should have a type
             */
            $docComment = $param->getDocComment();
            if ($docComment instanceof Doc && str_contains($docComment->getText(), '@deprecated tag:v6.7.0')) {
                continue;
            }

            $var = $param->var;
            if (!$var instanceof Variable) {
                continue;
            }
            $name = $var->name;
            if (!\is_string($name)) {
                continue;
            }

            // Check constructor doc comment, as resource and callable cannot be typed natively
            $methodDocComment = $constructor->getDocComment();
            if ($methodDocComment instanceof Doc
                && (str_contains($methodDocComment->getText(), \sprintf('@param resource $%s', $name))
                    || str_contains($methodDocComment->getText(), \sprintf('@param callable $%s', $name)))
            ) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf('Native type for property "%s" is missing', $name))
                ->identifier('shopware.propertyNativeType')
                ->line($param->getLine())
                ->build();
        }

        return $errors;
    }
}
