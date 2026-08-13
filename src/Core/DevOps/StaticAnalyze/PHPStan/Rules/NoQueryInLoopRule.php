<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use Shopware\Core\Framework\Log\Package;

/**
 * Detects the N+1 query problem: a database query that is executed once per loop iteration instead of loading the
 * data for all iterations up front.
 *
 * Which calls are inside a loop is provided by {@see LoopContextVisitor}, which has to be registered as
 * `phpstan.parser.richParserNodeVisitor`.
 *
 * @phpstan-import-type LoopContext from LoopContextVisitor
 *
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoQueryInLoopRule implements Rule
{
    use InTestClassTrait;

    public function __construct(private readonly QueryCallDetector $detector)
    {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        /** @var list<LoopContext> $loops */
        $loops = $node->getAttribute(LoopContextVisitor::ATTRIBUTE, []);

        if ($loops === [] || $this->isInTestClass($scope) || $this->detector->isExcludedClass($scope)) {
            return [];
        }

        $queryClass = $this->detector->getQueryClass($node->name->toString(), $scope->getType($node->var));

        if ($queryClass === null) {
            return [];
        }

        if (!$this->detector->scalesWithRecords($loops, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                '%s::%s() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                $this->detector->shortClassName($queryClass),
                $node->name->toString()
            ))
                ->identifier('shopware.queryInLoop')
                ->build(),
        ];
    }
}
