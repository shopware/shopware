<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig\RequestStackPushCollector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig\SalesChannelContextAttributeCollector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig\SharedTwigOrigin;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig\TwigEnvironmentOriginCollector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig\TwigRenderCollector;
use Shopware\Core\Framework\Log\Package;

/**
 * Twig resolves globals once per environment. A render through the container's `twig` without a Storefront
 * request therefore caches `TemplateDataExtension`'s globals empty for every later test.
 *
 * Reported: such renders (on `twig` or a template it handed out) in test classes where no method pushes a
 * request carrying a sales channel context. Own environments, other container environments and untraceable
 * references are not reported.
 *
 * @phpstan-import-type TwigRenderData from TwigRenderCollector
 * @phpstan-import-type TwigEnvironmentOriginData from TwigEnvironmentOriginCollector
 *
 * @implements Rule<CollectedDataNode>
 *
 * @internal
 */
#[Package('framework')]
class NoSharedTwigRenderWithoutRequestRule implements Rule
{
    public const ERROR = '%s renders through the shared `twig` environment without a Storefront request, caching empty Storefront globals for every later test. Render through an own Environment, or push a request carrying a sales channel context first.';

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @param CollectedDataNode $node
     *
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // `Class::method` of the methods that push a request and put a sales channel context on one
        $storefrontRequestMethods = array_intersect_key(
            $this->methodSet($node, RequestStackPushCollector::class),
            $this->methodSet($node, SalesChannelContextAttributeCollector::class),
        );
        $storefrontRequestClasses = [];
        foreach (array_keys($storefrontRequestMethods) as $method) {
            $storefrontRequestClasses[explode('::', $method, 2)[0]] = true;
        }

        /** @var array<string, array<string, array<string, true>>> $origins class => reference key => origins */
        $origins = [];
        foreach ($node->get(TwigEnvironmentOriginCollector::class) as $entries) {
            /** @var TwigEnvironmentOriginData $entry */
            foreach ($entries as $entry) {
                $origins[$entry['class']][$entry['key']][$entry['origin']] = true;
            }
        }

        $errors = [];
        foreach ($node->get(TwigRenderCollector::class) as $file => $renders) {
            /** @var TwigRenderData $render */
            foreach ($renders as $render) {
                if (!$this->rendersThroughSharedEnvironment($render, $origins)) {
                    continue;
                }

                if ($this->inHierarchy($render['hierarchy'], $storefrontRequestClasses)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(\sprintf(self::ERROR, $render['call']))
                    ->file($file)
                    ->line($render['line'])
                    ->identifier('shopware.sharedTwigRenderWithoutRequest')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @param class-string<RequestStackPushCollector|SalesChannelContextAttributeCollector> $collector
     *
     * @return array<string, true>
     */
    private function methodSet(CollectedDataNode $node, string $collector): array
    {
        $set = [];
        foreach ($node->get($collector) as $methods) {
            foreach ($methods as $method) {
                $set[$method] = true;
            }
        }

        return $set;
    }

    /**
     * @param list<string> $hierarchy
     * @param array<string, true> $classes
     */
    private function inHierarchy(array $hierarchy, array $classes): bool
    {
        foreach ($hierarchy as $class) {
            if (isset($classes[$class])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param TwigRenderData $render
     * @param array<string, array<string, array<string, true>>> $origins
     */
    private function rendersThroughSharedEnvironment(array $render, array $origins): bool
    {
        if ($render['shared']) {
            return true;
        }

        $key = $render['key'];
        if ($key === null) {
            return false;
        }

        // only report what is proven: every assignment of the reference resolves to the container lookup
        return $this->resolveOrigins($key, $render, $origins, 0) === [SharedTwigOrigin::SHARED => true];
    }

    /**
     * The terminal origins of a reference, following `$this->twig = $twig;` aliases, over the whole class
     * hierarchy: variable keys carry their function name, and conflicting origins at different levels
     * leave the reference unproven instead of guessing.
     *
     * @param TwigRenderData $render
     * @param array<string, array<string, array<string, true>>> $origins
     *
     * @return array<string, true>
     */
    private function resolveOrigins(string $key, array $render, array $origins, int $depth): array
    {
        if ($depth > 5) {
            // alias cycle or an unusually long chain: nothing is proven
            return [SharedTwigOrigin::OTHER => true];
        }

        $found = [];
        foreach ($render['hierarchy'] as $class) {
            foreach (array_keys($origins[$class][$key] ?? []) as $origin) {
                if (!str_starts_with($origin, SharedTwigOrigin::ALIAS_PREFIX)) {
                    $found[$origin] = true;

                    continue;
                }

                $source = substr($origin, \strlen(SharedTwigOrigin::ALIAS_PREFIX));
                $found += $this->resolveOrigins($source, $render, $origins, $depth + 1);
            }
        }

        return $found;
    }
}
