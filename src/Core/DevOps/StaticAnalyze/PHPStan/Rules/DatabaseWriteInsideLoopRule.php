<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;

/**
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class DatabaseWriteInsideLoopRule implements Rule
{
    /**
     * @var string[]
     */
    private array $forbiddenMethods = [
        'executeStatement',
        'insert',
        'create',
        'delete',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        if (!\in_array($methodName, $this->forbiddenMethods, true)) {
            return [];
        }

        $calledOnType = $scope->getType($node->var);

        /** @phpstan-ignore phpstanApi.instanceofType */
        if ($calledOnType->isObject()->no() || !$calledOnType instanceof ObjectType) {
            return [];
        }

        $classNames = $calledOnType->getObjectClassNames();

        if (\in_array(MultiInsertQueryQueue::class, $classNames, true)) {
            return [];
        }

        $connectionCall = $calledOnType->isInstanceOf(Connection::class)->yes();
        $repositoryCall = $calledOnType->isInstanceOf(EntityRepository::class)->yes();
        $salesChannelRepositoryCall = $calledOnType->isInstanceOf(SalesChannelRepository::class)->yes();

        if (!$connectionCall && !$repositoryCall && !$salesChannelRepositoryCall) {
            return [];
        }

        $callee = $scope->getFunction();
        /** @phpstan-ignore phpstanApi.runtimeReflection */
        $refMethod = new \ReflectionMethod(PhpFunctionFromParserNodeReflection::class, 'getFunctionLike');
        $refMethod->setAccessible(true); // bypass visibility
        $result = $refMethod->invoke($callee);

        $nodeVisitor = new class extends NodeVisitorAbstract {
            public function enterNode(Node $node): Node
            {
                if ($node->hasAttribute('parent')) {
                    return $node;
                }

                foreach ($node->getSubNodeNames() as $name) {
                    if (!property_exists($node, $name)) {
                        continue;
                    }

                    /** @phpstan-ignore symplify.noDynamicName */
                    $child = $node->$name;

                    if (\is_array($child)) {
                        foreach ($child as $c) {
                            if ($c instanceof Node) {
                                if ($c->hasAttribute('parent')) {
                                    continue;
                                }
                                $this->enterNode($c);
                                $c->setAttribute('parent', $node);
                            }
                        }
                    } elseif ($child instanceof Node) {
                        if ($child->hasAttribute('parent')) {
                            continue;
                        }
                        $this->enterNode($child);
                        $child->setAttribute('parent', $node);
                    }
                }

                return $node;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($nodeVisitor);

        if (!$result instanceof ClassMethod || $result->stmts === null) {
            return [];
        }

        $traverser->traverse($result->stmts);

        $isInsideLoop = $this->isInsideLoop($node);

        if (!$isInsideLoop) {
            return [];
        }

        $calleeClass = $calledOnType->getClassName();

        if ($methodName === 'executeStatement') {
            $statement = $node->getArgs()[0]->value ?? null;

            // check if is a database write statement
            if ($statement instanceof Node\Scalar\String_) {
                $query = trim($statement->value);
                if (stripos($query, 'INSERT') === 0 || stripos($query, 'DELETE') === 0) {
                    $action = mb_substr($query, 0, 6);

                    $errorMessage = \sprintf(
                        'Calling %s::%s() with %s statement is not recommended.',
                        $calleeClass,
                        'executeStatement',
                        mb_substr($query, 0, 6)
                    );

                    if ($action === 'INSERT') {
                        $errorMessage .= \sprintf(', please use `%s` instead.', MultiInsertQueryQueue::class);
                    }

                    return [
                        RuleErrorBuilder::message($errorMessage)->identifier('shopware.avoidDatabaseWriteInLoop')->build(),
                    ];
                }
            }

            return [];
        }

        if ($methodName === 'insert') {
            return [
                RuleErrorBuilder::message(\sprintf(
                    'Calling %s::%s() in a loop is not recommended, please use `%s`.',
                    $calleeClass,
                    'insert',
                    MultiInsertQueryQueue::class
                ))->identifier('shopware.avoidDatabaseWriteInLoop')->build(),
            ];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Calling %s::%s() in a loop is not recommended.',
                $calleeClass,
                $methodName
            ))->identifier('shopware.avoidDatabaseWriteInLoop')->build(),
        ];
    }

    private function isInsideLoop(Node $node): bool
    {
        /** @phpstan-ignore phpParser.nodeConnectingAttribute */
        $parent = $node->getAttribute('parent');

        if ($parent === null) {
            return false;
        }

        while ($parent instanceof Node) {
            // exclude while and do-while loops because usually they are used for batching/chunking strategy
            if (
                $parent instanceof For_
                || $parent instanceof Foreach_
            ) {
                return true;
            }

            // Check for closures or arrow functions inside array_* calls
            if ($parent instanceof Closure || $parent instanceof ArrowFunction) {
                /** @phpstan-ignore phpParser.nodeConnectingAttribute */
                $closureParent = $parent->getAttribute('parent');

                if ($closureParent instanceof Node\Arg && $closureParent->hasAttribute('parent')) {
                    /** @phpstan-ignore phpParser.nodeConnectingAttribute */
                    $closureParent = $closureParent->getAttribute('parent');
                }

                if (
                    $closureParent instanceof FuncCall
                    && $closureParent->name instanceof Node\Name
                ) {
                    return true;
                }
            }

            /** @phpstan-ignore phpParser.nodeConnectingAttribute */
            $parent = $parent->getAttribute('parent');
        }

        return false;
    }
}
