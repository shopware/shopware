<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('framework')]
class PublicServiceDecoratorRule implements Rule
{
    public function __construct(private readonly ServiceMap $serviceMap)
    {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof InClassNode) {
            return [];
        }

        $reflection = $node->getClassReflection();

        if (TestRuleHelper::isTestClass($reflection)) {
            return [];
        }

        $className = $reflection->getName();
        $service = $this->serviceMap->getService($className);

        if ($service === null) {
            return [];
        }

        if (empty($service->getTags())) {
            return [];
        }

        $decorates = null;

        foreach ($service->getTags() as $tag) {
            /** @phpstan-ignore phpstanApi.method */
            if ($tag->getName() === 'container.decorator') {
                /** @phpstan-ignore phpstanApi.method */
                $decorates = $tag->getAttributes()['id'] ?? null;

                break;
            }
        }

        if ($decorates === null) {
            return [];
        }

        $decorated = $this->serviceMap->getService($decorates);

        if ($decorated === null) {
            return [
                RuleErrorBuilder::message(
                    \sprintf(
                        'Service "%s" is a decorator for "%s", but the decorated service does not exist.',
                        $className,
                        $decorates
                    )
                )
                ->identifier('shopware.publicServiceDecorator')
                ->build(),
            ];
        }

        if (!$decorated->isPublic()) {
            return [];
        }

        if ($service->isPublic()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                \sprintf(
                    'Service "%s" decorates the public service "%s" but is not marked as public. Decorators of public services must also be public.',
                    $className,
                    $decorated->getId()
                )
            )
            ->identifier('shopware.publicServiceDecorator')
            ->build(),
        ];
    }
}
