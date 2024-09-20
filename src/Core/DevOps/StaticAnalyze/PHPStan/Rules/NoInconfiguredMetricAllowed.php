<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Symfony\ParameterMap;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @implements Rule<New_>
 *
 * @important: this check only focuses on string literals, if the metric name is dynamic, this check will not work.
 *
 * This implementation relies on an 'internal' phpstan-symfony call. This is a quick solution to fetch the parameters from the containers
 * If this test breaks, it is likely due to a change in the phpstan-symfony package. Most of it would still be covered in the integration tests, so there are no adverse effects to the codebase.
 * This is only considered because the last changes in the interfaces is ~2-3 years ago, and the interfaces are simple (don't see a case where they might break)
 *
 * See: tests/integration/Core/Framework/Telemetry/EventTelemetryFlowTest.php (as a test that holds partial example coverage)
 * All other cases are implicitly covered by other integration tests, since when adding an inconfigured Metric, the test would be expected to fail with an exception.
 *
 * @internal
 */
#[Package('core')]
class NoInconfiguredMetricAllowed implements Rule
{
    use InTestClassTrait;

    /**
     * @var array<string, true>
     */
    private array $definitions;

    public function __construct(ParameterMap $parameterMap)
    {
        /** @phpstan-ignore-next-line ignore possible breaking change */
        $paramValues = $parameterMap->getParameter('shopware.telemetry.metrics.definitions')?->getValue() ?? [];
        $this->definitions = array_fill_keys(array_keys((array) $paramValues), true);
    }

    public function getNodeType(): string
    {
        return New_::class;
    }

    /**
     * @param New_ $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInTestClass($scope)) {
            // disregard test cases, as those could include mocks and dummy data
            return [];
        }

        if (!$node instanceof New_) {
            return [];
        }

        if (!$node->class instanceof Name) {
            return [];
        }

        if ((string) $node->class !== ConfiguredMetric::class) {
            return [];
        }

        $nameArgument = null;
        foreach ($node->args as $argument) {
            if ($argument instanceof Arg && $argument->name instanceof Identifier && $argument->name->name === 'name') {
                $nameArgument = $argument;
                break;
            }
        }

        $nameArgument = $nameArgument ?? ($node->args[0] ?? null);
        // metric name is dynamic, fetched from a property or a method or else, for simplicity, we'll only check for string literals.
        if (!$nameArgument instanceof Arg || !$nameArgument->value instanceof String_) {
            return [];
        }

        $metricName = $nameArgument->value->value;
        if (!\array_key_exists($metricName, $this->definitions)) {
            return [\sprintf('Metric "%s" is not configured', $metricName)];
        }

        return [];
    }
}
