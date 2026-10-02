<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Log\Package;

/**
 * Reports `preg_*` patterns whose body ends with an unescaped `$` or `\Z` while the modifiers carry neither `D`
 * nor `m`: such a pattern also matches before a trailing newline. Patterns are read from constant strings, inline
 * `sprintf()` formats, concatenations, interpolated strings and the last local assignment in the enclosing
 * function. A call whose pattern stays unreadable is reported as unresolved, to be restructured or allowlisted
 * per file with the reason.
 *
 * Not detected: an anchor inside a trailing group or alternation, and a runtime modifier part carrying `D` or `m`.
 *
 * @internal
 *
 * @implements Rule<FuncCall>
 */
#[Package('framework')]
class PregDollarWithoutDRule implements Rule
{
    public const IDENTIFIER = 'shopware.pregDollarWithoutD';

    public const IDENTIFIER_UNRESOLVED = 'shopware.pregDollarWithoutD.unresolved';

    public const ERROR = '%s(): pattern "%s" ends with an anchor that also matches before a trailing newline. Add the D modifier or anchor with \z.';

    public const ERROR_UNRESOLVED = '%s(): the pattern cannot be resolved statically. Make it visible at the call, or allowlist the file with the reason.';

    /**
     * Functions taking the pattern (or an array of patterns) as their first argument.
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
     * Functions taking the patterns as the keys of their first argument.
     */
    private const PATTERN_KEY_FUNCTIONS = [
        'preg_replace_callback_array',
    ];

    /**
     * Stands in for a runtime part of the pattern while the template is inspected.
     */
    private const RUNTIME = "\0";

    private const MAX_TEMPLATES = 16;

    private const MAX_VARIABLE_DEPTH = 3;

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
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return [];
        }

        $functionName = strtolower(ltrim($node->name->toString(), '\\'));
        $patternsAreKeys = \in_array($functionName, self::PATTERN_KEY_FUNCTIONS, true);
        if (!$patternsAreKeys && !\in_array($functionName, self::PATTERN_ARGUMENT_FUNCTIONS, true)) {
            return [];
        }

        if (!$this->isEnabledFor($scope->getNamespace())) {
            return [];
        }

        $patternArg = $node->getArgs()[0] ?? null;
        if ($patternArg === null) {
            return [];
        }

        $templates = $patternsAreKeys
            ? $this->resolveKeyTemplates($patternArg->value, $scope)
            : $this->resolveTemplates($patternArg->value, $scope, $node, 0);

        if ($templates === []) {
            return [
                RuleErrorBuilder::message(\sprintf(self::ERROR_UNRESOLVED, $functionName))
                    ->identifier(self::IDENTIFIER_UNRESOLVED)
                    ->build(),
            ];
        }

        $errors = [];
        foreach ($templates as $template) {
            if (!$this->endsWithNewlineTolerantAnchor($template)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                self::ERROR,
                $functionName,
                str_replace(self::RUNTIME, '{…}', $template),
            ))->identifier(self::IDENTIFIER)->build();
        }

        return $errors;
    }

    private function isEnabledFor(?string $namespace): bool
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
     * @return list<string> templates with runtime placeholders
     */
    private function resolveTemplates(Expr $expr, Scope $scope, FuncCall $call, int $depth): array
    {
        $templates = $this->constantTemplates($scope->getType($expr));
        if ($templates !== []) {
            return $templates;
        }

        if ($expr instanceof Array_) {
            foreach ($expr->items as $item) {
                foreach ($this->resolveTemplates($item->value, $scope, $call, $depth) as $template) {
                    $templates[] = $template;
                }
            }

            return $templates;
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name && strtolower(ltrim($expr->name->toString(), '\\')) === 'sprintf') {
            $formatArg = $expr->getArgs()[0] ?? null;
            if ($formatArg === null) {
                return [];
            }

            foreach ($this->resolveTemplates($formatArg->value, $scope, $call, $depth) as $format) {
                $templates[] = $this->formatToTemplate($format);
            }

            return $templates;
        }

        if ($expr instanceof Concat) {
            $lefts = $this->resolveTemplates($expr->left, $scope, $call, $depth) ?: [self::RUNTIME];
            $rights = $this->resolveTemplates($expr->right, $scope, $call, $depth) ?: [self::RUNTIME];

            foreach ($lefts as $left) {
                foreach ($rights as $right) {
                    $templates[] = $left . $right;
                    if (\count($templates) >= self::MAX_TEMPLATES) {
                        break 2;
                    }
                }
            }

            // a concatenation of only runtime parts tells nothing
            return array_values(array_filter($templates, static fn (string $template) => trim($template, self::RUNTIME) !== ''));
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

            return trim($template, self::RUNTIME) === '' ? [] : [$template];
        }

        if ($expr instanceof Variable && \is_string($expr->name) && $depth < self::MAX_VARIABLE_DEPTH) {
            $assigned = $this->findLastAssignment($expr->name, $scope->getFile(), $call);
            if ($assigned === null) {
                return [];
            }

            return $this->resolveTemplates($assigned, $scope, $call, $depth + 1);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function resolveKeyTemplates(Expr $expr, Scope $scope): array
    {
        $templates = [];
        foreach ($scope->getType($expr)->getConstantArrays() as $constantArray) {
            foreach ($constantArray->getKeyTypes() as $keyType) {
                $templates = [...$templates, ...$this->constantTemplates($keyType)];
            }
        }

        return $templates;
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
     * Finds the last `$name = …` in the innermost function-like body enclosing the call, before the call line.
     */
    private function findLastAssignment(string $name, string $file, FuncCall $call): ?Expr
    {
        try {
            $stmts = $this->parser->parseFile($file);
        } catch (\Throwable) {
            return null;
        }

        $finder = new NodeFinder();
        $callLine = $call->getStartLine();

        $enclosing = null;
        foreach ($finder->findInstanceOf($stmts, FunctionLike::class) as $functionLike) {
            if ($functionLike->getStartLine() > $callLine || $functionLike->getEndLine() < $callLine) {
                continue;
            }
            if ($enclosing === null || $functionLike->getStartLine() >= $enclosing->getStartLine()) {
                $enclosing = $functionLike;
            }
        }

        if ($enclosing === null) {
            return null;
        }

        $assigned = null;
        foreach ($finder->findInstanceOf($enclosing, Assign::class) as $assign) {
            if (!$assign->var instanceof Variable || $assign->var->name !== $name) {
                continue;
            }
            if ($assign->getStartLine() > $callLine) {
                continue;
            }
            // the call may itself be the right-hand side of the assignment
            if ($assign->getStartLine() === $callLine && $assign->expr === $call) {
                continue;
            }
            $assigned = $assign->expr;
        }

        return $assigned;
    }

    /**
     * Replaces sprintf conversion specifications with runtime placeholders and unescapes `%%`.
     */
    private function formatToTemplate(string $format): string
    {
        $template = preg_replace('/%(?:\d+\$)?[-+ 0]*(?:\'.)?\d*(?:\.\d+)?[bcdeEfFgGhHosuxX]/', self::RUNTIME, $format) ?? $format;

        return str_replace('%%', '%', $template);
    }

    /**
     * `$` and `\Z` both match before a final newline unless `D` is set; `m` turns `$` into a per-line anchor on purpose.
     */
    private function endsWithNewlineTolerantAnchor(string $template): bool
    {
        $template = ltrim($template);
        if ($template === '' || $template[0] === self::RUNTIME) {
            return false;
        }

        $delimiter = $template[0];
        if (ctype_alnum($delimiter) || $delimiter === '\\') {
            return false;
        }
        $closing = self::CLOSING_DELIMITERS[$delimiter] ?? $delimiter;

        $end = strrpos($template, $closing, 1);
        if ($end === false || $end === 0) {
            return false;
        }

        $body = substr($template, 1, $end - 1);
        $modifiers = str_replace(self::RUNTIME, '', substr($template, $end + 1));

        if (str_contains($modifiers, 'D') || str_contains($modifiers, 'm')) {
            return false;
        }

        if (str_ends_with($body, '$')) {
            // an even number of backslashes before the dollar leaves it unescaped
            return $this->countTrailingBackslashes(substr($body, 0, -1)) % 2 === 0;
        }

        if (str_ends_with($body, '\\Z')) {
            // an odd number of backslashes before the Z makes it the anchor
            return $this->countTrailingBackslashes(substr($body, 0, -1)) % 2 === 1;
        }

        return false;
    }

    private function countTrailingBackslashes(string $value): int
    {
        return \strlen($value) - \strlen(rtrim($value, '\\'));
    }
}
