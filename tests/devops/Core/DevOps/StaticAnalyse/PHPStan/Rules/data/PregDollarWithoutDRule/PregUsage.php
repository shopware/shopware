<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PregDollarWithoutDRule;

class PregUsage
{
    final public const VALID_PATTERN = '^[0-9a-f]{32}$';

    public function literals(string $value): void
    {
        preg_match('/^[a-z]+$/', $value); // flagged: constant
        preg_match('/^[a-z]+$/D', $value); // ok: D
        preg_match('/^[a-z]+$/mi', $value); // ok: m
        preg_match('/^[a-z]+\$/', $value); // ok: escaped dollar
        preg_match('/^[a-z]+\\\\$/', $value); // flagged: escaped backslash, then an anchor
        preg_match('/^[a-z]+\z/', $value); // ok: \z
        preg_match('/^[a-z]+\Z/', $value); // flagged: \Z behaves like $
        preg_match('/^[a-z]+\\\\Z/', $value); // ok: escaped backslash, then a literal Z
        preg_match('~^[a-z]+$~i', $value); // flagged: other delimiter
        preg_match('{^[a-z]+$}', $value); // flagged: bracket delimiter
        preg_match('/^[a-z]+$ /x', $value); // flagged: extended mode ignores the trailing whitespace
        \preg_match_all('#\d+$#', $value); // flagged: fully qualified call
        preg_match('/(a|b$)/', $value); // NOT detected: dollar inside a trailing group (known gap)
        preg_split('/,$/', $value); // flagged
        preg_grep('/x$/', [$value]); // flagged
        preg_filter('/y$/', '', $value); // flagged
        preg_match(subject: $value, pattern: '/^n$/'); // flagged: named arguments
    }

    public function classConstant(string $value): void
    {
        preg_match('/' . self::VALID_PATTERN . '/', $value); // flagged: class constant folds to a constant string
    }

    public function localVariable(string $value): void
    {
        $regex = '/^foo$/';
        preg_match($regex, $value); // flagged: variable narrowed to a literal by the scope
    }

    public function sprintfInline(string $email, string $value): void
    {
        preg_match(\sprintf('/^%s$/i', $email), $value); // flagged: sprintf format
        preg_match(\sprintf('/^%s$/Di', $email), $value); // ok
        preg_match(\sprintf('/^%1$s-%2$05d$/', $email, 3), $value); // flagged: positional specs
        preg_match(\sprintf('/^%%s$/', $email), $value); // flagged: an escaped percent sign is kept as is
    }

    public function sprintfViaVariable(string $email, string $value): void
    {
        $regex = \sprintf('/^%s$/i', $email);

        preg_match($regex, $value); // flagged: assignment resolved through the enclosing method
    }

    public function concatenation(string $pattern, string $value, string $modifiers): void
    {
        preg_match('/^' . $pattern . '$/', $value); // flagged: literal tail
        preg_match('/^' . $pattern . '$/D', $value); // ok
        preg_match('/^' . $pattern . '/', $value); // ok: ends in a runtime part
        preg_match("/^{$pattern}$/" . $modifiers, $value); // flagged: dynamic modifiers
        preg_match("/^{$pattern}$/D" . $modifiers, $value); // ok
        preg_match($modifiers . '^a$' . $modifiers, $value); // unresolved: runtime delimiters
    }

    /**
     * @param 'a'|'b'|'c' $union
     */
    public function unionTypedPart(string $union, string $value): void
    {
        preg_match('/' . $union . '$/', $value); // flagged once, listing every pattern
    }

    public function arrays(string $value, string $pattern): void
    {
        preg_replace(['/^a$/', '/b$/D'], '', $value); // flagged once, for the first pattern
        preg_replace(['/^c$/D', '/^' . $pattern . '$/'], '', $value); // flagged: the concatenated item next to a constant one
        preg_replace_callback_array(['/^d$/' => static fn (array $m): string => '', '/e$/D' => static fn (array $m): string => ''], $value); // flagged once, for the first key
    }

    public function rebuiltVariable(string $pattern, string $value): void
    {
        $regex = '/^' . $pattern;
        $regex .= '$/';
        preg_match($regex, $value); // flagged: the appended tail is part of the pattern

        $wrapped = '(?:' . $pattern . ')';
        $wrapped = '/^' . $wrapped . '$/';
        preg_match($wrapped, $value); // flagged: the self-referencing assignment resolves to its predecessor

        $late = \sprintf('/^%s$/', $pattern);
        preg_match($late, $value);
        $late = '/^z$/D'; // flagged: the assignment after the call does not count
    }

    public function capturedVariable(string $pattern, string $value): void
    {
        $regex = \sprintf('/^%s$/', $pattern);

        $arrow = fn (): int => preg_match($regex, $value); // flagged: read through the arrow function
        $closure = static function () use ($regex, $value): int {
            return preg_match($regex, $value); // flagged: read through the closure capture
        };
        $shadowing = static function () use ($pattern, $value): int {
            $regex = \sprintf('/^%s$/D', $pattern);

            return preg_match($regex, $value); // ok: the closure-local variable shadows the outer one
        };
        $arrow();
        $closure();
        $shadowing();
    }

    public function unresolved(string $pattern, string $value): void
    {
        preg_match($pattern, $value); // unresolved: parameter
        preg_match($this->buildPattern(), $value); // unresolved: method result
    }

    private function buildPattern(): string
    {
        return '/x$/';
    }
}
