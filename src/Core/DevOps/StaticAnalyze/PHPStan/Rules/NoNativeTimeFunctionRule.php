<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<FuncCall>
 */
#[Package('framework')]
class NoNativeTimeFunctionRule implements Rule
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

    private const NOT_ALLOWED_FUNCTIONS = [
        'time',
        'microtime',
        'hrtime',
        'strtotime',
        'date_create',
        'date_create_immutable',
    ];

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return [];
        }

        if (!\in_array(strtolower($node->name->toString()), self::NOT_ALLOWED_FUNCTIONS, true)) {
            return [];
        }

        if (!$this->reflectionProvider->hasFunction($node->name, $scope)) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction($node->name, $scope);
        if (!$function->isBuiltin()) {
            return [];
        }

        if ($this->isPathExempt($scope)) {
            return [];
        }

        $functionName = strtolower($function->getName());

        // time() / microtime() / hrtime() always read wall-clock. strtotime() is
        // conditional on its arguments, and date_create() / date_create_immutable()
        // are procedural equivalents of `new DateTime*` — all three delegate to
        // helpers that mirror the class rule's string-analysis policy.
        if ($functionName === 'strtotime') {
            return $this->checkStrtotime($node, $scope);
        }

        if ($functionName === 'date_create' || $functionName === 'date_create_immutable') {
            return $this->checkDateTimeFactory($node, $scope);
        }

        return $this->buildError();
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
     * Mirrors NoNativeTimeClassRule's handling for `new DateTime* ` — these
     * procedural factories have the same contract: first positional or a
     * `datetime:` named arg carries the datetime string; absence defaults to "now";
     * a DateTimeZone second arg never affects whether the current clock is read.
     *
     * @return list<IdentifierRuleError>
     */
    private function checkDateTimeFactory(FuncCall $node, Scope $scope): array
    {
        $args = $node->getArgs();

        // date_create() / date_create_immutable() — zero args defaults to "now".
        if ($args === []) {
            return $this->buildError();
        }

        // Locate the datetime argument: first positional, or a "datetime:" named
        // arg. A call with only an unrelated named arg (e.g. `timezone: $tz`)
        // omits the datetime string — factory falls back to "now".
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

        // Loose policy: only inspect literal string arguments.
        if (!$datetimeArg instanceof String_) {
            return [];
        }

        if (NativeTimeReadHelper::readsCurrentTime($datetimeArg->value)) {
            return $this->buildError();
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkStrtotime(FuncCall $node, Scope $scope): array
    {
        $args = $node->getArgs();

        // Missing datetime — runtime error territory; let PHP handle it.
        if ($args === []) {
            return [];
        }

        // strtotime(string $datetime, ?int $baseTimestamp = null)
        // Resolve both args honoring positional and named syntax.
        $datetimeArg = null;
        $baseArg = null;
        $positional = 0;
        foreach ($args as $arg) {
            if ($arg->name === null) {
                if ($positional === 0) {
                    $datetimeArg = $arg->value;
                } elseif ($positional === 1) {
                    $baseArg = $arg->value;
                }
                ++$positional;

                continue;
            }

            $paramName = $arg->name->toString();
            if ($paramName === 'datetime') {
                $datetimeArg = $arg->value;
            } elseif ($paramName === 'baseTimestamp') {
                $baseArg = $arg->value;
            }
        }

        if ($datetimeArg === null) {
            return [];
        }

        // Loose policy: only inspect literal string arguments. Variables and
        // other computed expressions are trusted and skipped.
        if (!$datetimeArg instanceof String_) {
            return [];
        }

        // An explicit base that isn't definitely null replaces time() — trust
        // the caller. Only a literal null base falls through to the string check.
        if ($baseArg !== null && !$scope->getType($baseArg)->isNull()->yes()) {
            return [];
        }

        if (NativeTimeReadHelper::readsCurrentTime($datetimeArg->value)) {
            return $this->buildError();
        }

        return [];
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
