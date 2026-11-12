<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Reports patterns that also match before a trailing newline: an unescaped `$` without the `D` modifier, or `\Z`.
 * A `$` under `m` (or inline `(?m)`) is skipped, it asks for per-line anchors and PCRE ignores `D` with `m`.
 * `\Z` is reported whatever the modifiers are, neither `D` nor `m` changes it.
 * Covers `preg_*`, the Symfony `Regex` constraint (not with `match: false`) and the Finder regex filters.
 * A `preg_*` or `Regex` pattern that cannot be read completely is reported as unresolved: no readable template,
 * an unreadable array item, more than 16 concatenated variants, or writes to the variable that depend on a branch.
 * Not covered: runtime modifiers, `#[Regex]` attributes, Twig's `matches` operator, calls through `call_user_func()`,
 * and what a runtime part of a pattern contributes, such as its own `$` or a top-level `|`.
 *
 * @internal
 *
 * @implements Rule<CallLike>
 */
#[Package('framework')]
class PregDollarWithoutDRule implements Rule
{
    public const IDENTIFIER = 'shopware.pregDollarWithoutD';

    public const IDENTIFIER_UNRESOLVED = 'shopware.pregDollarWithoutD.unresolved';

    public const ERROR = '%s(): %s also matches before a trailing newline. Anchor with \z, or add the D modifier when the anchor is $.';

    public const ERROR_UNRESOLVED = '%s(): the pattern cannot be resolved statically. Make it visible at the call, or allowlist the file with the reason.';

    private const PATTERN_ARGUMENT_FUNCTIONS = [
        'preg_match',
        'preg_match_all',
        'preg_replace',
        'preg_replace_callback',
        'preg_split',
        'preg_grep',
        'preg_filter',
    ];

    private const PATTERN_KEY_FUNCTIONS = [
        'preg_replace_callback_array',
    ];

    private const FINDER_METHODS = ['name', 'notname', 'path', 'notpath', 'contains', 'notcontains'];

    private const TRIMMING_NORMALIZERS = ['trim', 'rtrim'];

    /**
     * Placeholder for a runtime part of the pattern.
     */
    private const RUNTIME = "\0";

    /**
     * Caps the concatenation cross product.
     */
    private const MAX_TEMPLATES = 16;

    private const CLOSING_DELIMITERS = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];

    /**
     * @var list<string>
     */
    private readonly array $enabledNamespaces;

    public function __construct(
        Configuration $configuration,
        private readonly Parser $parser,
    ) {
        $this->enabledNamespaces = $configuration->getPregDollarWithoutDEnabledNamespaces();
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof CallLike || $node->isFirstClassCallable() || !$this->isEnabledNamespace($scope->getNamespace())) {
            return [];
        }

        $call = $this->patternCall($node, $scope);
        if ($call === null) {
            return [];
        }

        $incomplete = false;
        $templates = $call['keys']
            ? $this->keyTemplates($scope->getType($call['pattern']))
            : $this->resolveTemplates($call['pattern'], $scope, $incomplete);

        $offending = [];
        $unknown = $templates === [] || $incomplete;
        foreach ($templates as $template) {
            if ($call['finder'] && !self::isFinderRegex($template)) {
                continue;
            }

            $verdict = $this->hasNewlineTolerantAnchor($template);
            if ($verdict === null) {
                $unknown = true;
            } elseif ($verdict) {
                $offending[] = '"' . str_replace(self::RUNTIME, '{…}', $template) . '"';
            }
        }

        if ($offending !== []) {
            $offending = array_values(array_unique($offending));
            $subject = \count($offending) === 1
                ? 'pattern ' . $offending[0]
                : 'each of the patterns ' . implode(', ', $offending);

            return [
                RuleErrorBuilder::message(\sprintf(self::ERROR, $call['label'], $subject))
                    ->identifier(self::IDENTIFIER)
                    ->build(),
            ];
        }

        // Finder reads what it cannot recognise as a glob
        if ($unknown && !$call['finder']) {
            return [
                RuleErrorBuilder::message(\sprintf(self::ERROR_UNRESOLVED, $call['label']))
                    ->identifier(self::IDENTIFIER_UNRESOLVED)
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * @return array{label: string, pattern: Expr, keys: bool, finder: bool}|null
     */
    private function patternCall(CallLike $call, Scope $scope): ?array
    {
        if ($call instanceof FuncCall && $call->name instanceof Name) {
            $function = $call->name->toLowerString();
            $keys = \in_array($function, self::PATTERN_KEY_FUNCTIONS, true);
            if (!$keys && !\in_array($function, self::PATTERN_ARGUMENT_FUNCTIONS, true)) {
                return null;
            }

            $pattern = self::argument($call, 'pattern');

            return $pattern === null ? null : ['label' => $function, 'pattern' => $pattern, 'keys' => $keys, 'finder' => false];
        }

        if ($call instanceof New_ && $call->class instanceof Name) {
            $class = new ObjectType($scope->resolveName($call->class));
            if (!(new ObjectType(Regex::class))->isSuperTypeOf($class)->yes()) {
                return null;
            }

            $pattern = self::argument($call, 'pattern');
            $match = self::argument($call, 'match', 3);
            $normalizer = self::argument($call, 'normalizer', 4);
            if ($pattern instanceof Array_) {
                // legacy options array
                $match ??= self::arrayItem($pattern, 'match');
                $normalizer ??= self::arrayItem($pattern, 'normalizer');
                $pattern = self::arrayItem($pattern, 'pattern');
            }

            if ($normalizer !== null && \in_array($this->constantString($normalizer, $scope), self::TRIMMING_NORMALIZERS, true)) {
                return null;
            }

            // with match: false the constraint fails on a match, so D would accept more values instead of fewer
            if ($match !== null && $scope->getType($match)->isFalse()->yes()) {
                return null;
            }

            return $pattern === null ? null : ['label' => 'Regex', 'pattern' => $pattern, 'keys' => false, 'finder' => false];
        }

        if ($call instanceof MethodCall && $call->name instanceof Identifier) {
            $method = $call->name->toLowerString();
            if (!\in_array($method, self::FINDER_METHODS, true)
                || !(new ObjectType(Finder::class))->isSuperTypeOf($scope->getType($call->var))->yes()
            ) {
                return null;
            }

            $pattern = self::argument($call, 'patterns') ?? self::argument($call, 'pattern');

            return $pattern === null ? null : ['label' => 'Finder::' . $call->name->toString(), 'pattern' => $pattern, 'keys' => false, 'finder' => true];
        }

        return null;
    }

    private function constantString(Expr $expr, Scope $scope): ?string
    {
        $strings = $scope->getType($expr)->getConstantStrings();

        return \count($strings) === 1 ? $strings[0]->getValue() : null;
    }

    private function isEnabledNamespace(?string $namespace): bool
    {
        if ($namespace === null) {
            return false;
        }

        foreach ($this->enabledNamespaces as $enabledNamespace) {
            if (str_starts_with($namespace . '\\', $enabledNamespace)) {
                return true;
            }
        }

        return false;
    }

    private static function argument(CallLike $call, string $name, int $position = 0): ?Expr
    {
        $args = $call->getArgs();
        foreach ($args as $arg) {
            if ($arg->name?->toString() === $name) {
                return $arg->value;
            }
        }

        return isset($args[$position]) && $args[$position]->name === null ? $args[$position]->value : null;
    }

    private static function arrayItem(Array_ $array, string $key): ?Expr
    {
        foreach ($array->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === $key) {
                return $item->value;
            }
        }

        return null;
    }

    /**
     * Sets $incomplete when part of the pattern could not be read, so a clean remainder proves nothing.
     * $call is set while a write is replayed: scope types then hold only for variables not written since.
     *
     * @return list<string> templates with runtime placeholders
     */
    private function resolveTemplates(Expr $expr, Scope $scope, bool &$incomplete, ?Node $call = null): array
    {
        if ($expr instanceof Array_) {
            $templates = [];
            foreach ($expr->items as $item) {
                $itemTemplates = $this->resolveTemplates($item->value, $scope, $incomplete, $call);
                if ($itemTemplates === []) {
                    $incomplete = true;
                }
                $templates = [...$templates, ...$itemTemplates];
            }

            return $templates;
        }

        if ($call === null || $this->isUnchangedUntil($expr, $scope, $call)) {
            $templates = $this->constantTemplates($scope->getType($expr));
            if ($templates !== []) {
                return $templates;
            }
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name && $expr->name->toLowerString() === 'sprintf') {
            $format = self::argument($expr, 'format');
            if ($format === null) {
                return [];
            }

            $values = [];
            foreach ($expr->getArgs() as $arg) {
                if ($arg->value !== $format) {
                    $values[] = $arg->name === null ? $this->replayedConstantString($arg, $scope, $call) : null;
                }
            }

            return array_map(
                fn (string $template): string => $this->formatToTemplate($template, $values),
                $this->resolveTemplates($format, $scope, $incomplete, $call)
            );
        }

        if ($expr instanceof Concat) {
            return self::concatTemplates(
                $this->resolveTemplates($expr->left, $scope, $incomplete, $call),
                $this->resolveTemplates($expr->right, $scope, $incomplete, $call),
                $incomplete,
            );
        }

        if ($expr instanceof InterpolatedString) {
            $template = '';
            foreach ($expr->parts as $part) {
                if ($part instanceof InterpolatedStringPart) {
                    $template .= $part->value;

                    continue;
                }

                $partStrings = $call === null || $this->isUnchangedUntil($part, $scope, $call)
                    ? $scope->getType($part)->getConstantStrings()
                    : [];
                $template .= \count($partStrings) === 1 ? $partStrings[0]->getValue() : self::RUNTIME;
            }

            return self::withoutPureRuntime([$template]);
        }

        if ($expr instanceof Variable && \is_string($expr->name)) {
            return $this->resolveVariable($expr, $scope, $incomplete, $call);
        }

        return [];
    }

    private function replayedConstantString(Arg $arg, Scope $scope, ?Node $call): ?string
    {
        return $call === null || $this->isUnchangedUntil($arg->value, $scope, $call)
            ? $this->constantString($arg->value, $scope)
            : null;
    }

    /**
     * @return list<string>
     */
    private function constantTemplates(Type $type): array
    {
        $templates = [];
        foreach ($type->getConstantStrings() as $constantString) {
            $templates[] = $constantString->getValue();
        }
        foreach ($type->getConstantArrays() as $constantArray) {
            foreach ($constantArray->getValueTypes() as $valueType) {
                foreach ($valueType->getConstantStrings() as $constantString) {
                    $templates[] = $constantString->getValue();
                }
            }
        }

        return $templates;
    }

    /**
     * @return list<string>
     */
    private function keyTemplates(Type $type): array
    {
        $templates = [];
        foreach ($type->getConstantArrays() as $constantArray) {
            foreach ($constantArray->getKeyTypes() as $keyType) {
                foreach ($keyType->getConstantStrings() as $constantString) {
                    $templates[] = $constantString->getValue();
                }
            }
        }

        return $templates;
    }

    /**
     * Replays the last assignment before the read, then each `.=` after it.
     *
     * @return list<string>
     */
    private function resolveVariable(Variable $variable, Scope $scope, bool &$incomplete, ?Node $call): array
    {
        \assert(\is_string($variable->name));

        ['writes' => $writes, 'conditional' => $conditional] = $this->findWrites($variable->name, $scope->getFile(), $variable);
        if ($writes === []) {
            return $call !== null && $this->isUnchangedUntil($variable, $scope, $call)
                ? $this->constantTemplates($scope->getType($variable))
                : [];
        }
        if ($conditional) {
            $incomplete = true;
        }

        $templates = [];
        foreach ($writes as $write) {
            $templates = $write instanceof Assign
                ? $this->resolveTemplates($write->expr, $scope, $incomplete, $call ?? $variable)
                : self::concatTemplates($templates, $this->resolveTemplates($write->expr, $scope, $incomplete, $call ?? $variable), $incomplete);
        }

        return $templates;
    }

    /**
     * True when no variable read by $expr is written between $expr and $call, so its type at $call still applies.
     */
    private function isUnchangedUntil(Expr $expr, Scope $scope, Node $call): bool
    {
        foreach ((new NodeFinder())->findInstanceOf([$expr], Variable::class) as $read) {
            if (!\is_string($read->name)) {
                return false;
            }

            foreach ($this->findWrites($read->name, $scope->getFile(), $call)['writes'] as $write) {
                if ($write->getStartFilePos() >= $expr->getStartFilePos()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Writes before the read in the owning function; arrow functions and capturing closures continue outward.
     * `conditional` is set when a write sits in a branch or loop that does not also hold the read.
     *
     * @return array{writes: list<Assign|AssignOp\Concat>, conditional: bool}
     */
    private function findWrites(string $name, string $file, Node $read): array
    {
        $none = ['writes' => [], 'conditional' => false];

        try {
            $stmts = $this->parser->parseFile($file);
        } catch (\Throwable) {
            return $none;
        }

        $functions = array_values((new NodeFinder())->findInstanceOf($stmts, FunctionLike::class));
        $before = $read;
        while (true) {
            $owner = self::innermostFunction($functions, $before);
            $writes = self::writesBefore($owner ?? $stmts, $name, $before->getStartFilePos());
            if ($writes['writes'] !== [] || $owner === null) {
                return $writes;
            }

            $captures = $owner instanceof ArrowFunction
                || ($owner instanceof Closure && \in_array($name, array_map(static fn ($use) => $use->var->name, $owner->uses), true));
            if (!$captures) {
                return $none;
            }

            $before = $owner;
        }
    }

    /**
     * @param list<FunctionLike> $functions
     */
    private static function innermostFunction(array $functions, Node $node): ?FunctionLike
    {
        $innermost = null;
        foreach ($functions as $function) {
            if ($function === $node || $function->getStartFilePos() > $node->getStartFilePos() || $function->getEndFilePos() < $node->getEndFilePos()) {
                continue;
            }
            if ($innermost === null || $function->getStartFilePos() > $innermost->getStartFilePos()) {
                $innermost = $function;
            }
        }

        return $innermost;
    }

    /**
     * @param Node|array<Node> $root
     *
     * @return array{writes: list<Assign|AssignOp\Concat>, conditional: bool}
     */
    private static function writesBefore(Node|array $root, string $name, int $position): array
    {
        $visitor = new class($root, $name, $position) extends NodeVisitorAbstract {
            /**
             * @var list<Assign|AssignOp\Concat>
             */
            public array $writes = [];

            /**
             * Source ranges that only run on some paths: branch bodies, loop bodies, right-hand operands.
             *
             * @var list<array{int, int}>
             */
            public array $arms = [];

            /**
             * @param Node|array<Node> $root
             */
            public function __construct(
                private readonly Node|array $root,
                private readonly string $name,
                private readonly int $position,
            ) {
            }

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof FunctionLike && $node !== $this->root) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                foreach (self::conditionalParts($node) as $part) {
                    $this->arms[] = $part;
                }

                if (($node instanceof Assign || $node instanceof AssignOp\Concat)
                    && $node->var instanceof Variable
                    && $node->var->name === $this->name
                    && $node->getEndFilePos() < $this->position
                ) {
                    $this->writes[] = $node;
                }

                return null;
            }

            /**
             * @return list<array{int, int}>
             */
            private static function conditionalParts(Node $node): array
            {
                $parts = match (true) {
                    $node instanceof Stmt\If_, $node instanceof Stmt\ElseIf_, $node instanceof Stmt\Else_,
                    $node instanceof Stmt\Case_, $node instanceof Stmt\Catch_,
                    $node instanceof Stmt\For_, $node instanceof Stmt\Foreach_, $node instanceof Stmt\While_, $node instanceof Stmt\Do_ => [$node->stmts],
                    $node instanceof Ternary => [$node->if, $node->else],
                    $node instanceof BinaryOp\Coalesce, $node instanceof BinaryOp\BooleanAnd, $node instanceof BinaryOp\BooleanOr,
                    $node instanceof BinaryOp\LogicalAnd, $node instanceof BinaryOp\LogicalOr => [$node->right],
                    $node instanceof MatchArm => [$node->body],
                    default => [],
                };

                $ranges = [];
                foreach ($parts as $part) {
                    $nodes = array_values(array_filter(\is_array($part) ? $part : [$part]));
                    if ($nodes === []) {
                        continue;
                    }
                    $ranges[] = [$nodes[0]->getStartFilePos(), $nodes[\count($nodes) - 1]->getEndFilePos()];
                }

                return $ranges;
            }
        };

        (new NodeTraverser($visitor))->traverse(\is_array($root) ? $root : [$root]);

        $conditional = false;
        foreach ($visitor->writes as $write) {
            $arm = self::innermostRange($visitor->arms, $write->getStartFilePos());
            if ($arm !== null && ($position < $arm[0] || $position > $arm[1])) {
                $conditional = true;
            }
        }

        return ['writes' => $visitor->writes, 'conditional' => $conditional];
    }

    /**
     * @param list<array{int, int}> $ranges
     *
     * @return array{int, int}|null
     */
    private static function innermostRange(array $ranges, int $position): ?array
    {
        $innermost = null;
        foreach ($ranges as $range) {
            if ($range[0] <= $position && $position <= $range[1]
                && ($innermost === null || $range[1] - $range[0] < $innermost[1] - $innermost[0])
            ) {
                $innermost = $range;
            }
        }

        return $innermost;
    }

    /**
     * An unreadable side counts as one runtime part.
     *
     * @param list<string> $lefts
     * @param list<string> $rights
     *
     * @return list<string>
     */
    private static function concatTemplates(array $lefts, array $rights, bool &$incomplete): array
    {
        $templates = [];
        foreach ($lefts ?: [self::RUNTIME] as $left) {
            foreach ($rights ?: [self::RUNTIME] as $right) {
                if (\count($templates) >= self::MAX_TEMPLATES) {
                    // the variants past the cap are not checked
                    $incomplete = true;

                    break 2;
                }
                $templates[] = $left . $right;
            }
        }

        return self::withoutPureRuntime($templates);
    }

    /**
     * @param list<string> $templates
     *
     * @return list<string>
     */
    private static function withoutPureRuntime(array $templates): array
    {
        return array_values(array_filter($templates, static fn (string $template) => trim($template, self::RUNTIME) !== ''));
    }

    /**
     * A plain `%s` takes its value when the argument is a constant string, any other conversion is a runtime part.
     *
     * @param list<string|null> $values
     */
    private function formatToTemplate(string $format, array $values): string
    {
        $next = 0;

        return preg_replace_callback(
            '/%(?:%|(?:(\d+)\$)?([-+ 0]*(?:\'.)?\d*(?:\.\d+)?)([bcdeEfFgGhHosuxX]))/',
            static function (array $match) use ($values, &$next): string {
                if ($match[0] === '%%') {
                    return '%';
                }

                $position = $match[1] ?? '';
                $index = $position !== '' ? (int) $position - 1 : $next++;

                return ($match[3] ?? '') === 's' && ($match[2] ?? '') === '' ? ($values[$index] ?? self::RUNTIME) : self::RUNTIME;
            },
            $format
        ) ?? $format;
    }

    /**
     * Null when the delimiters are runtime parts.
     * PHPStan's RegexExpressionHelper parses delimiters too, but is not public API.
     */
    private function hasNewlineTolerantAnchor(string $template): ?bool
    {
        $template = ltrim($template);
        if ($template === '' || $template[0] === self::RUNTIME) {
            return null;
        }

        $delimiter = $template[0];
        if (ctype_alnum($delimiter) || $delimiter === '\\') {
            // not a valid pattern, PHPStan's own regexp rule reports it
            return false;
        }
        $closing = self::CLOSING_DELIMITERS[$delimiter] ?? $delimiter;

        $end = strrpos($template, $closing, 1);
        if ($end === false) {
            return null;
        }

        $body = substr($template, 1, $end - 1);
        $modifiers = str_replace(self::RUNTIME, '', substr($template, $end + 1));

        return self::containsNewlineTolerantAnchor(
            $body,
            str_contains($modifiers, 'D'),
            str_contains($modifiers, 'm'),
            str_contains($modifiers, 'x'),
        );
    }

    /**
     * `$` counts unless D or a multiline flag applies, `\Z` always counts.
     * Inline flags like `(?m)` apply to the rest of their group, `(?m:...)` only inside it.
     */
    private static function containsNewlineTolerantAnchor(string $body, bool $dollarEndOnly, bool $multiline, bool $extended): bool
    {
        $flags = [['m' => $multiline, 'x' => $extended]];
        $length = \strlen($body);
        for ($i = 0; $i < $length; ++$i) {
            $char = $body[$i];
            $current = $flags[\count($flags) - 1];

            if ($char === '\\') {
                $next = $body[$i + 1] ?? '';
                if ($next === 'Z') {
                    return true;
                }
                if ($next === 'Q') {
                    $quoteEnd = strpos($body, '\\E', $i + 2);
                    if ($quoteEnd === false) {
                        // everything after an unclosed \Q is literal
                        return false;
                    }
                    $i = $quoteEnd + 1;

                    continue;
                }
                ++$i;

                continue;
            }

            if ($char === '[') {
                $i = self::endOfCharacterClass($body, $i);

                continue;
            }

            if ($char === '(') {
                if (substr($body, $i, 3) === '(?#') {
                    $commentEnd = strpos($body, ')', $i);
                    if ($commentEnd === false) {
                        return false;
                    }
                    $i = $commentEnd;

                    continue;
                }

                if (preg_match('/\G\(\?([a-zA-Z]*)(?:-([a-zA-Z]*))?([:)])/', $body, $match, 0, $i) === 1) {
                    $set = self::withInlineFlags($current, $match[1], $match[2]);
                    if ($match[3] === ')') {
                        $flags[\count($flags) - 1] = $set;
                    } else {
                        $flags[] = $set;
                    }
                    $i += \strlen($match[0]) - 1;

                    continue;
                }

                $flags[] = $current;

                continue;
            }

            if ($char === ')') {
                if (\count($flags) > 1) {
                    array_pop($flags);
                }

                continue;
            }

            if ($current['x'] && $char === '#') {
                $commentEnd = strpos($body, "\n", $i);
                if ($commentEnd === false) {
                    return false;
                }
                $i = $commentEnd;

                continue;
            }

            if ($char === '$' && !$dollarEndOnly && !$current['m']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{m: bool, x: bool} $flags
     *
     * @return array{m: bool, x: bool}
     */
    private static function withInlineFlags(array $flags, string $on, string $off): array
    {
        foreach (['m', 'x'] as $flag) {
            if (str_contains($on, $flag)) {
                $flags[$flag] = true;
            }
            if (str_contains($off, $flag)) {
                $flags[$flag] = false;
            }
        }

        return $flags;
    }

    /**
     * A `]` right after `[` or `[^` is literal.
     */
    private static function endOfCharacterClass(string $body, int $start): int
    {
        $length = \strlen($body);
        $i = $start + 1;
        if (($body[$i] ?? '') === '^') {
            ++$i;
        }
        if (($body[$i] ?? '') === ']') {
            ++$i;
        }

        for (; $i < $length; ++$i) {
            if ($body[$i] === '\\') {
                ++$i;

                continue;
            }

            if ($body[$i] === '[' && ($body[$i + 1] ?? '') === ':') {
                $posixEnd = strpos($body, ':]', $i + 2);
                if ($posixEnd !== false) {
                    $i = $posixEnd + 1;

                    continue;
                }
            }

            if ($body[$i] === ']') {
                return $i;
            }
        }

        return $length;
    }

    /**
     * Mirrors Symfony's MultiplePcreFilterIterator::isRegex().
     */
    private static function isFinderRegex(string $template): bool
    {
        if (str_contains($template, self::RUNTIME) || preg_match('/^(.{3,}?)[imsxuADUn]*$/Ds', $template, $match) !== 1) {
            return false;
        }

        $start = $match[1][0];
        $end = $match[1][-1];
        if ($start === $end) {
            return preg_match('/[*?[:alnum:] \\\\]/', $start) !== 1;
        }

        return \in_array([$start, $end], [['{', '}'], ['(', ')'], ['[', ']'], ['<', '>']], true);
    }
}
