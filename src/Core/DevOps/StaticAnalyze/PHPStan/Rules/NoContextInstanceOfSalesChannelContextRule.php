<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Instanceof_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<Instanceof_>
 */
#[Package('core')]
class NoContextInstanceOfSalesChannelContextRule implements Rule
{
    public function getNodeType(): string
    {
        return Instanceof_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Instanceof_) {
            return [];
        }

        $variableType = $scope->getType($node->expr);

        $contextType = new ObjectType('Shopware\\Core\\Framework\\Context');
        $salesChannelContextType = 'Shopware\\Core\\System\\SalesChannel\\SalesChannelContext';

        $errors = [];

        if ($contextType->isSuperTypeOf($variableType)->yes()
            && $node->class instanceof Node\Name
            && $node->class->toString() === $salesChannelContextType
        ) {
            $errors[] =
                RuleErrorBuilder::message(
                    'Usage of "Context instanceof SalesChannelContext" is forbidden when $context is explicitly typed as Context.'
                )
                ->line($node->getLine())
                ->identifier('shopware.noContextInstanceOfSalesChannelContext')
                ->build();
        }

        return $errors;
    }
}
