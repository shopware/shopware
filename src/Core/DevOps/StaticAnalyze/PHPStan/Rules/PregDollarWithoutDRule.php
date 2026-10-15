<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
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
 * Reports patterns containing an unescaped `$` or `\Z` outside character classes, escapes and comments while the
 * modifiers carry neither `D` nor `m`: such a pattern also matches before a trailing newline. Checked are the
 * `preg_*` functions, the Symfony `Regex` constraint and the regex filters of the Symfony Finder (`name()`,
 * `notName()`, `path()`, `notPath()`, `contains()`, `notContains()`; a Finder argument that Finder itself would
 * read as a glob is skipped). Patterns are read from constant strings, inline `sprintf()` formats, concatenations,
 * interpolated strings and the assignments to a local variable that precede the call. A `preg_*` call or `Regex`
 * constraint whose pattern stays unreadable is reported as unresolved, to be restructured or allowlisted per file
 * with the reason.
 *
 * Not detected: a runtime modifier part carrying `D` or `m`, and the constraint used as an attribute.
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

    public const ERROR = '%s(): %s also matches before a trailing newline. Add the D modifier or anchor with \z.';

    public const ERROR_UNRESOLVED = '%s(): the pattern cannot be resolved statically. Make it visible at the call, or allowlist the file with the reason.';

    /**
     * Functions taking the pattern (or an array of patterns) as their `pattern` argument.
     */
    private const PATTERN_ARGUMENT_FUNCTIONS = [
        'preg_match',
        'preg_match_all',
        'preg_replace',
        'preg_replace_callback',
        'preg_split',
        'preg_grep',
        'preg_filter',
    ];

    /**
     * Functions taking the patterns as the keys of their `pattern` argument.
     */
    private const PATTERN_KEY_FUNCTIONS = [
        'preg_replace_callback_array',
    ];

    /**
     * Finder filters taking a pattern, or a list of patterns, that Finder applies with `preg_match` when it reads as a regex.
     */
    private const FINDER_METHODS = ['name', 'notname', 'path', 'notpath', 'contains', 'notcontains'];

    /**
     * Constraint normalizers that strip the trailing newline before the pattern is applied.
     */
    private const TRIMMING_NORMALIZERS = ['trim', 'rtrim'];

    /**
     * Stands in for a runtime part of the pattern while the template is inspected.
     */
    private const RUNTIME = "\0";

    /**
     * Caps the cross product of union-typed concatenation parts.
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

        $templates = $call['keys']
            ? $this->keyTemplates($scope->getType($call['pattern']))
            : $this->resolveTemplates($call['pattern'], $scope);

        $offending = [];
        $unknown = $templates === [];
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

        // Finder reads anything it cannot recognise as a regex as a glob, so an unreadable argument tells nothing
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
     * The pattern argument of a call that applies it as a regular expression, null for any other call.
     *
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

            $normalizer = self::argument($call, 'normalizer', 4);
            if ($normalizer !== null && \in_array($this->constantString($normalizer, $scope), self::TRIMMING_NORMALIZERS, true)) {
                return null;
            }

            $pattern = self::argument($call, 'pattern');
            if ($pattern instanceof Array_) {
                // legacy options array: `new Regex(['pattern' => ...])`
                $pattern = self::arrayItem($pattern, 'pattern');
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

    /**
     * The named argument if the call uses one, otherwise the positional argument at the given index.
     */
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
     * @return list<string> templates with runtime placeholders
     */
    private function resolveTemplates(Expr $expr, Scope $scope): array
    {
        if ($expr instanceof Array_) {
            $templates = [];
            foreach ($expr->items as $item) {
                $templates = [...$templates, ...$this->resolveTemplates($item->value, $scope)];
            }

            return $templates;
        }

        $templates = $this->constantTemplates($scope->getType($expr));
        if ($templates !== []) {
            return $templates;
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name && $expr->name->toLowerString() === 'sprintf') {
            $format = self::argument($expr, 'format');

            return $format === null ? [] : array_map($this->formatToTemplate(...), $this->resolveTemplates($format, $scope));
        }

        if ($expr instanceof Concat) {
            return self::concatTemplates(
                $this->resolveTemplates($expr->left, $scope),
                $this->resolveTemplates($expr->right, $scope),
            );
        }

        if ($expr instanceof InterpolatedString) {
            $template = '';
            foreach ($expr->parts as $part) {
                if ($part instanceof InterpolatedStringPart) {
                    $template .= $part->value;

                    continue;
                }

                $partStrings = $scope->getType($part)->getConstantStrings();
                $template .= \count($partStrings) === 1 ? $partStrings[0]->getValue() : self::RUNTIME;
            }

            return self::withoutPureRuntime([$template]);
        }

        if ($expr instanceof Variable && \is_string($expr->name)) {
            return $this->resolveVariable($expr, $scope);
        }

        return [];
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
     * Replays the writes to the variable that precede its read: the last plain assignment, then every `.=` after it.
     *
     * @return list<string>
     */
    private function resolveVariable(Variable $variable, Scope $scope): array
    {
        \assert(\is_string($variable->name));

        $writes = $this->findWrites($variable->name, $scope->getFile(), $variable);
        if ($writes === []) {
            return [];
        }

        $templates = [];
        foreach ($writes as $write) {
            $templates = $write instanceof Assign
                ? $this->resolveTemplates($write->expr, $scope)
                : self::concatTemplates($templates, $this->resolveTemplates($write->expr, $scope));
        }

        return $templates;
    }

    /**
     * Writes to `$name` in the function body owning the read, in source order, nested functions excluded. A read
     * inside an arrow function, or inside a closure that captures the variable, continues in the enclosing body.
     * Only writes located before the read count, so a self-referencing assignment resolves to its predecessor.
     *
     * @return list<Assign|AssignOp\Concat>
     */
    private function findWrites(string $name, string $file, Expr $read): array
    {
        try {
            $stmts = $this->parser->parseFile($file);
        } catch (\Throwable) {
            return [];
        }

        $functions = array_values((new NodeFinder())->findInstanceOf($stmts, FunctionLike::class));
        $before = $read;
        while (true) {
            $owner = self::innermostFunction($functions, $before);
            $writes = self::writesBefore($owner ?? $stmts, $name, $before->getStartFilePos());
            if ($writes !== [] || $owner === null) {
                return $writes;
            }

            $captures = $owner instanceof ArrowFunction
                || ($owner instanceof Closure && \in_array($name, array_map(static fn ($use) => $use->var->name, $owner->uses), true));
            if (!$captures) {
                return [];
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
     * @return list<Assign|AssignOp\Concat>
     */
    private static function writesBefore(Node|array $root, string $name, int $position): array
    {
        $visitor = new class($root, $name, $position) extends NodeVisitorAbstract {
            /**
             * @var list<Assign|AssignOp\Concat>
             */
            public array $writes = [];

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

                if (($node instanceof Assign || $node instanceof AssignOp\Concat)
                    && $node->var instanceof Variable
                    && $node->var->name === $this->name
                    && $node->getEndFilePos() < $this->position
                ) {
                    $this->writes[] = $node;
                }

                return null;
            }
        };

        (new NodeTraverser($visitor))->traverse(\is_array($root) ? $root : [$root]);

        return $visitor->writes;
    }

    /**
     * Cross product of the two sides; a side that could not be read counts as one runtime part.
     *
     * @param list<string> $lefts
     * @param list<string> $rights
     *
     * @return list<string>
     */
    private static function concatTemplates(array $lefts, array $rights): array
    {
        $templates = [];
        foreach ($lefts ?: [self::RUNTIME] as $left) {
            foreach ($rights ?: [self::RUNTIME] as $right) {
                $templates[] = $left . $right;
                if (\count($templates) >= self::MAX_TEMPLATES) {
                    break 2;
                }
            }
        }

        return self::withoutPureRuntime($templates);
    }

    /**
     * A template made of runtime parts only tells nothing.
     *
     * @param list<string> $templates
     *
     * @return list<string>
     */
    private static function withoutPureRuntime(array $templates): array
    {
        return array_values(array_filter($templates, static fn (string $template) => trim($template, self::RUNTIME) !== ''));
    }

    /**
     * Replaces sprintf conversion specifications with runtime placeholders and unescapes `%%`.
     */
    private function formatToTemplate(string $format): string
    {
        return preg_replace_callback(
            '/%(%|(?:\d+\$)?[-+ 0]*(?:\'.)?\d*(?:\.\d+)?[bcdeEfFgGhHosuxX])/',
            static fn (array $match): string => $match[1] === '%' ? '%' : self::RUNTIME,
            $format
        ) ?? $format;
    }

    /**
     * `$` and `\Z` both match before a final newline unless `D` is set; `m` turns `$` into a per-line anchor on
     * purpose. Returns null when the delimiters cannot be told apart from runtime parts. PHPStan ships the same
     * delimiter parsing in its RegexExpressionHelper, which is not part of its public API.
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

        if (str_contains($modifiers, 'D') || str_contains($modifiers, 'm') || self::setsInlineMultiline($body)) {
            return false;
        }

        return self::containsNewlineTolerantAnchor($body, str_contains($modifiers, 'x'));
    }

    /**
     * An unescaped `$` or `\Z` anywhere outside a character class, `\Q…\E` or a comment is an end anchor: inside an
     * alternation or a group it still accepts a trailing newline.
     */
    private static function containsNewlineTolerantAnchor(string $body, bool $extended): bool
    {
        $length = \strlen($body);
        for ($i = 0; $i < $length; ++$i) {
            $char = $body[$i];

            if ($char === '\\') {
                $next = $body[$i + 1] ?? '';
                if ($next === 'Z') {
                    return true;
                }
                if ($next === 'Q') {
                    $quoteEnd = strpos($body, '\\E', $i + 2);
                    if ($quoteEnd === false) {
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

            if ($char === '(' && substr($body, $i, 3) === '(?#') {
                $commentEnd = strpos($body, ')', $i);
                if ($commentEnd === false) {
                    return false;
                }
                $i = $commentEnd;

                continue;
            }

            if ($extended && $char === '#') {
                $commentEnd = strpos($body, "\n", $i);
                if ($commentEnd === false) {
                    return false;
                }
                $i = $commentEnd;

                continue;
            }

            if ($char === '$') {
                return true;
            }
        }

        return false;
    }

    /**
     * Position of the `]` closing the character class opened at `$start`; a `]` right after `[` or `[^` is literal.
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
     * `(?m)` or `(?m:…)` switches the multiline mode on inline.
     */
    private static function setsInlineMultiline(string $body): bool
    {
        return preg_match('/\(\?[a-zA-Z]*m[a-zA-Z]*(?:-[a-zA-Z]*)?[):]/', $body) === 1;
    }

    /**
     * Mirrors Symfony's MultiplePcreFilterIterator::isRegex(): Finder applies anything else as a glob.
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
