<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * Collects the assignments of variables and properties that a test later renders through, tagged by
 * where the value comes from. Every assignment in a test class counts, so abstract base classes that
 * set a shared property up for their subclasses are covered too.
 *
 * @phpstan-type TwigEnvironmentOriginData array{class: string, key: string, origin: string}
 *
 * @implements Collector<Assign, TwigEnvironmentOriginData>
 *
 * @internal
 */
#[Package('framework')]
class TwigEnvironmentOriginCollector implements Collector
{
    public function getNodeType(): string
    {
        return Assign::class;
    }

    /**
     * @param Assign $node
     *
     * @return TwigEnvironmentOriginData|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        $class = $scope->getClassReflection();
        if ($class === null || !TestRuleHelper::isTestClass($class)) {
            return null;
        }

        $key = SharedTwigOrigin::referenceKey($node->var, $scope);
        if ($key === null) {
            return null;
        }

        // `$template = $twig->createTemplate('...');` — the template renders through $twig, so it takes $twig's origin
        $source = SharedTwigOrigin::templateSource($node->expr, $scope) ?? $node->expr;

        if (SharedTwigOrigin::isSharedTwigLookup($source, $scope)) {
            // counts whatever the lookup's declared type is: `assertInstanceOf()` narrows it later
            $origin = SharedTwigOrigin::SHARED;
        } elseif (SharedTwigOrigin::isContainerGet($source, $scope) || SharedTwigOrigin::isNewEnvironment($source, $scope)) {
            // another container service (e.g. the SEO URL environment) or an environment built by the test
            $origin = SharedTwigOrigin::OWN;
        } elseif (($sourceKey = SharedTwigOrigin::referenceKey($source, $scope)) !== null) {
            // `$this->twig = $twig;` — the rule follows the alias to wherever $twig came from
            $origin = SharedTwigOrigin::ALIAS_PREFIX . $sourceKey;
        } elseif (SharedTwigOrigin::isEnvironment($source, $scope)) {
            $origin = SharedTwigOrigin::OTHER;
        } else {
            return null;
        }

        return [
            'class' => $class->getName(),
            'key' => $key,
            'origin' => $origin,
        ];
    }
}
