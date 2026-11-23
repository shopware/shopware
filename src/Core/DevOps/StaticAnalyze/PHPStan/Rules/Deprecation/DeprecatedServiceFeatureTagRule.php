<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Adapter\Twig\Extension\CompatTwigExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\TwigFunction;

/**
 * Ensures services using deprecated classes are removed with their announced feature flag.
 * Compares the class's deprecation tag version with the shopware.inactiveFeature tag on each
 * matching service in PHPStan's compiled service map. For removed Twig extensions, also checks
 * literal function declarations against the compatibility extension's map for that flag.
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
        $removedTwigExtension = false;

        foreach ($this->serviceMap->getServices() as $service) {
            if ($service->getAlias() !== null || $service->getClass() !== $class->getName() || str_starts_with($service->getId(), 'sales_channel_definition.')) {
                continue;
            }

            $hasInactiveFeatureTag = false;
            $isTwigExtension = false;

            foreach ($service->getTags() as $tag) {
                /** @phpstan-ignore phpstanApi.method */
                $tagName = $tag->getName();
                if ($tagName === 'twig.extension') {
                    $isTwigExtension = true;
                }

                if ($tagName !== 'shopware.inactiveFeature') {
                    continue;
                }

                /** @phpstan-ignore phpstanApi.method */
                if (($tag->getAttributes()['flag'] ?? null) === $flag) {
                    $hasInactiveFeatureTag = true;
                }
            }

            if ($hasInactiveFeatureTag) {
                $removedTwigExtension = $removedTwigExtension || $isTwigExtension;

                continue;
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

        if (!$removedTwigExtension) {
            return $errors;
        }

        $method = $node->getOriginalNode()->getMethod('getFunctions');
        if ($method?->stmts === null) {
            return $errors;
        }

        foreach ((new NodeFinder())->findInstanceOf($method->stmts, New_::class) as $new) {
            if (!$new->class instanceof Name || $scope->resolveName($new->class) !== TwigFunction::class) {
                continue;
            }

            $name = $new->getArgs()[0]->value ?? null;
            if (!$name instanceof String_ || \in_array($name->value, CompatTwigExtension::FUNCTIONS_BY_FEATURE[$flag] ?? [], true)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Deprecated Twig extension "%s" declares function "%s", which must be listed under "%s" in CompatTwigExtension::FUNCTIONS_BY_FEATURE.',
                $class->getName(),
                $name->value,
                $flag
            ))
                ->identifier('shopware.deprecatedTwigFunctionCompat')
                ->line($new->getStartLine())
                ->build();
        }

        return $errors;
    }
}
