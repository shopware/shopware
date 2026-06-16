<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<New_>
 */
#[Package('framework')]
class NoNativeTimeClassRule implements Rule
{
    /**
     * Paths exempt from this rule:
     *   - Migrations are one-off DB scripts run outside the app lifecycle; DI/clock is not available.
     *   - Hydrators parse stored datetime strings from DB rows, not the current clock.
     *   - Test fixtures often construct explicit datetime for assertions.
     *   - Profilers and the telemetry facade measure real elapsed time by definition.
     *   - Dev tooling (PHPStan rules, CLI commands) runs outside the app domain.
     */
    protected const EXEMPT_PATH_PATTERN = '#(/Migration/|Hydrator\.php$|/Test/|/tests/|/Profiling/|Profiler|Telemetry|/DevOps/)#';

    private const ERROR_MESSAGE = 'Do not use native time reads. They cannot be frozen in tests. Use Psr\Clock\ClockInterface to inject a controllable clock.';

    private const NOT_ALLOWED_CLASSES = [
        'datetime',
        'datetimeimmutable',
    ];

    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_ || !$node->class instanceof Name) {
            return [];
        }

        $className = strtolower(ltrim($scope->resolveName($node->class), '\\'));

        if (!\in_array($className, self::NOT_ALLOWED_CLASSES, true)) {
            return [];
        }

        if ($this->isPathExempt($scope)) {
            return [];
        }

        $args = $node->getArgs();

        // new DateTime() / new DateTimeImmutable() — zero args defaults to "now".
        if ($args === []) {
            return $this->buildError();
        }

        // Locate the datetime argument: first positional, or a "datetime:" named arg.
        // A call that passes only unrelated named args (e.g. `timezone: $tz`) omits
        // the datetime string — constructor falls back to "now".
        $datetimeArg = null;
        foreach ($args as $arg) {
            if ($arg->name === null || $arg->name->toString() === 'datetime') {
                $datetimeArg = $arg->value;

                break;
            }
        }

        if ($datetimeArg === null) {
            return $this->buildError();
        }

        // Only inspect literal string arguments. Variables, casts,
        // class constants, and computed expressions are trusted and skipped —
        // static analysis cannot distinguish a stored absolute datetime from a
        // forbidden "now" without false-positive risk.
        if (!$datetimeArg instanceof String_) {
            return [];
        }

        if (NativeTimeReadHelper::readsCurrentTime($datetimeArg->value)) {
            return $this->buildError();
        }

        return [];
    }

    /**
     * Virtual method — subclasses (e.g. tests) override this to change the
     * path-based exemption without needing to override the class constant.
     */
    protected function isPathExempt(Scope $scope): bool
    {
        return preg_match(self::EXEMPT_PATH_PATTERN, $scope->getFile()) === 1;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function buildError(): array
    {
        return [
            RuleErrorBuilder::message(self::ERROR_MESSAGE)
                ->identifier('shopware.noNativeTimeRead')
                ->build(),
        ];
    }
}
