<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<Node\Expr\StaticCall>
 */
#[Package('framework')]
final class TemplateContractRule implements Rule
{
    public function __construct(
        private readonly TemplateContracts $contracts,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Node\Name\FullyQualified || $node->class->toString() !== TemplateContracts::class
            || !$node->name instanceof Node\Identifier || $node->name->toString() !== 'context'
            || \count($node->getArgs()) < 6
        ) {
            return [];
        }
        $args = $node->getArgs();
        $sources = [$scope->getType($args[2]->value)];
        if ($scope->getType($args[3]->value)->isTrue()->yes()) {
            $sources[] = $scope->getType($args[1]->value);
        }
        $sources[] = $scope->getType(new Node\Expr\MethodCall($args[0]->value, 'getGlobals'));
        $sources[] = $this->contracts->getRequestGlobals();
        $errors = [];
        foreach ($scope->getType($args[4]->value)->getConstantStrings() as $name) {
            $template = $name->getValue();
            $this->contracts->project($template, $scope);
            array_push($errors, ...$this->contracts->validate($template, $sources));
        }

        return $errors;
    }
}
