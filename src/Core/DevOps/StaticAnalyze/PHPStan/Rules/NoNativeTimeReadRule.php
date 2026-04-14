<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
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
 * @implements Rule<Node\Expr>
 */
#[Package('framework')]
class NoNativeTimeReadRule implements Rule
{
    private const ERROR_MESSAGE = 'Do not use native time reads. Use Psr\Clock\ClockInterface instead.';

    private const NOT_ALLOWED_FUNCTIONS = [
        'time',
        'microtime',
        'strtotime',
    ];

    private const NOT_ALLOWED_CLASSES = [
        'datetime',
        'datetimeimmutable',
    ];

    private const EXEMPT_PATH_PATTERN = '#(/Migration/|Hydrator\.php$|/Test/|/tests/|/Profiling/|Profiler|/DevOps/)#';

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (preg_match(self::EXEMPT_PATH_PATTERN, $scope->getFile())) {
            return [];
        }

        if ($node instanceof New_) {
            return $this->checkNew($node, $scope);
        }

        if ($node instanceof FuncCall) {
            return $this->checkFuncCall($node, $scope);
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkNew(New_ $node, Scope $scope): array
    {
        if (!$node->class instanceof Name) {
            return [];
        }

        $className = strtolower(ltrim($scope->resolveName($node->class), '\\'));

        if (!\in_array($className, self::NOT_ALLOWED_CLASSES, true)) {
            return [];
        }

        $args = $node->getArgs();

        // new DateTime() / new DateTimeImmutable() — zero args
        if ($args === []) {
            return $this->buildError();
        }

        // new DateTime('now') / new DateTimeImmutable('now')
        if (isset($args[0]) && $args[0]->value instanceof String_ && strtolower($args[0]->value->value) === 'now') {
            return $this->buildError();
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkFuncCall(FuncCall $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        if (!$this->reflectionProvider->hasFunction($node->name, $scope)) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction($node->name, $scope);
        if (!$function->isBuiltin()) {
            return [];
        }

        if (!\in_array(strtolower($function->getName()), self::NOT_ALLOWED_FUNCTIONS, true)) {
            return [];
        }

        return $this->buildError();
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
