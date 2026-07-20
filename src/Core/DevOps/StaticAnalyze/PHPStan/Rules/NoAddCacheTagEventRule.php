<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * This rule prevents the individual usage of the AddCacheTagEvent and promotes the use of the CacheTagCollector->addTag method.
 *
 * @internal
 *
 * @implements Rule<New_>
 */
#[Package('framework')]
class NoAddCacheTagEventRule implements Rule
{
    private PrettyPrinter $printer;

    public function __construct()
    {
        $this->printer = new PrettyPrinter();
    }

    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_) {
            return [];
        }

        if (!$node->class instanceof Name) {
            return [];
        }

        if ($node->class->toString() !== AddCacheTagEvent::class) {
            return [];
        }

        // Allow only the new usage inside the CacheTagCollector
        $currentClass = $scope->getClassReflection();
        if ($currentClass === null || $currentClass->getName() !== CacheTagCollector::class) {
            $parts = [];
            /** @var Arg $arg */
            foreach ($node->args as $arg) {
                $prefix = $arg->byRef ? '&' : '';
                $prefix .= $arg->unpack ? '...' : '';
                $code = $this->printer->prettyPrint([$arg->value]);
                $parts[] = $prefix . $code;
            }
            $argsCode = implode(', ', $parts);

            return [
                RuleErrorBuilder::message(\sprintf(
                    'Direct instantiation of %s is forbidden; use %s->addTag(%s) instead.',
                    AddCacheTagEvent::class,
                    CacheTagCollector::class,
                    $argsCode,
                ))
                ->identifier('shopware.noAddCacheTagEvent')
                ->build(),
            ];
        }

        return [];
    }
}
