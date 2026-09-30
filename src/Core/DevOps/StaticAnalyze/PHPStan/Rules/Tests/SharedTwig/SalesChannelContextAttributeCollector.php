<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;

/**
 * Collects the methods of test classes that put a sales channel context onto a request, as `Class::method`:
 * `TemplateDataExtension` only returns its globals for a request carrying one, a bare pushed request resolves
 * them empty too. Any use of `PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT` counts
 * (`->attributes->set()`, an attributes array passed to the request constructor, ...).
 *
 * @implements Collector<ClassConstFetch, string>
 *
 * @internal
 */
#[Package('framework')]
class SalesChannelContextAttributeCollector implements Collector
{
    public function getNodeType(): string
    {
        return ClassConstFetch::class;
    }

    /**
     * @param ClassConstFetch $node
     */
    public function processNode(Node $node, Scope $scope): ?string
    {
        if (!$node->class instanceof Name || !$node->name instanceof Identifier || $node->name->name !== 'ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT') {
            return null;
        }

        if ($scope->resolveName($node->class) !== PlatformRequest::class) {
            return null;
        }

        $class = $scope->getClassReflection();
        if ($class === null || !TestRuleHelper::isTestClass($class)) {
            return null;
        }

        return $class->getName() . '::' . ($scope->getFunctionName() ?? '');
    }
}
