<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PregDollarWithoutDRule;

use Symfony\Component\Validator\Constraints\Regex;

class ReviewCases
{
    public function modifiers(string $value, string $pattern): void
    {
        // \Z ignores D and m, $ does not
        preg_match('/^a\Z/D', $value); // flagged: \Z with D
        preg_match('/^a\Z/m', $value); // flagged: \Z with m
        preg_match('/^a$/m', $value); // ok: per-line anchor
        preg_match(' /^a$/', $value); // flagged: leading whitespace before the delimiter
        preg_match('/a\Q$b/', $value); // ok: everything after an unclosed \Q is literal
        preg_match('/(?x)a # $ comment' . "\n" . 'b/', $value); // ok: comment under inline extended mode
        preg_match('/\(?m)a$/', $value); // flagged: escaped parenthesis, no inline flag
        preg_match('/(?:(?m)a)$/', $value); // flagged: the inline flag ends with its group
        preg_match(\sprintf('/^%s$/%s', $pattern, 'D'), $value); // ok: the constant argument fills in D
    }

    public function constraints(): void
    {
        new Regex('/^a$/', null, null, null, 'trim'); // ok: positional normalizer
        new Regex(['pattern' => '/^a$/', 'normalizer' => 'trim']); // ok: normalizer in the legacy options array
        new Regex(pattern: '#/$#', match: false); // ok: a negative check
        new Regex(['pattern' => '#/$#', 'match' => false]); // ok: a negative check in the legacy options array
    }

    public function incomplete(string $value, string $runtime, bool $flag): void
    {
        preg_replace(['/p$/D', $runtime], '', $value); // unresolved: one item cannot be read

        $branch = $flag ? '/^a$/' : '/^a$/D';
        preg_match($branch, $value); // flagged: a variant without D

        if ($flag) {
            $r = \sprintf('/^%s$/', $runtime);
        } else {
            $r = \sprintf('/^%s$/D', $runtime);
        }
        preg_match($r, $value); // unresolved: the writes depend on the branch

        $part = '$';
        $late = '/^' . $runtime . $part . '/';
        $part = 'b';
        preg_match($late, $value); // flagged: $part is read with the value it had at the assignment
    }

    /**
     * @param '1'|'2'|'3'|'4'|'5' $digit
     */
    public function capped(string $digit, string $value): void
    {
        preg_match('/' . $digit . $digit . $digit . $digit . '$/D', $value); // unresolved: past the 16-variant cap
    }
}

class NotAFinder
{
    public function name(string $pattern): self
    {
        return $this;
    }
}

class FinderLookalike
{
    public function filter(NotAFinder $object): void
    {
        $object->name('/\.php$/'); // ok: not a Finder
    }
}
