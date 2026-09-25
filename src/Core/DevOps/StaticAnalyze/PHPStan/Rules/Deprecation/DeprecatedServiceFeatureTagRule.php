<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;

/**
 * Ensures services using deprecated classes are removed with their announced feature flag.
 * Compares the class's deprecation tag version with the shopware.inactiveFeature tag on each
 * matching service in PHPStan's compiled service map.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class DeprecatedServiceFeatureTagRule implements Rule
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

        $class = $node->getClassReflection();
        $deprecation = $class->getDeprecatedDescription();
        if ($deprecation === null || !preg_match('/tag:(v\d+\.\d+\.\d+(?:\.\d+)?)/', $deprecation, $matches)) {
            return [];
        }

        $flag = substr_count($matches[1], '.') === 2 ? $matches[1] . '.0' : $matches[1];
        $errors = [];

        foreach ($this->serviceMap->getServices() as $service) {
            if ($service->getAlias() !== null || $service->getClass() !== $class->getName() || str_starts_with($service->getId(), 'sales_channel_definition.')) {
                continue;
            }

            foreach ($service->getTags() as $tag) {
                /** @phpstan-ignore phpstanApi.method */
                if ($tag->getName() !== 'shopware.inactiveFeature') {
                    continue;
                }

                /** @phpstan-ignore phpstanApi.method */
                if (($tag->getAttributes()['flag'] ?? null) === $flag) {
                    continue 2;
                }
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Service "%s" uses deprecated class "%s" and must be tagged "shopware.inactiveFeature" for "%s".',
                $service->getId(),
                $class->getName(),
                $flag
            ))
                ->identifier('shopware.deprecatedServiceFeatureTag')
                ->build();
        }

        return $errors;
    }
}
