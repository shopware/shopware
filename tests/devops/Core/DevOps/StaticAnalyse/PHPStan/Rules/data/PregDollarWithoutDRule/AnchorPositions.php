<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PregDollarWithoutDRule;

class AnchorPositions
{
    public function positions(string $value): void
    {
        // the anchor does not have to end the pattern
        // a leading comment line keeps the flagged lines aligned with the test
        preg_match('/^([a-z]{2,3})(?:[-_]|$)/i', $value); // flagged: anchor as a branch of an alternation
        preg_match('/^(?:a$|b)c?/', $value); // flagged: anchor inside a group
        preg_match('/a\Z|b/', $value); // flagged: \Z inside an alternation
        preg_match('/[$]/', $value); // ok: a dollar in a character class is a literal
        preg_match('/[^$x]/', $value); // ok: also in a negated class
        preg_match('/[]$]/', $value); // ok: a leading ] belongs to the class
        preg_match('/[[:alpha:]$]/', $value); // ok: POSIX class inside the class
        preg_match('/\Q$\E/', $value); // ok: quoted literal
        preg_match('/[$]$/', $value); // flagged: the second dollar is an anchor
        preg_match('/a(?# $ )b/', $value); // ok: inline comment
        preg_match("/a # $ comment\n b/x", $value); // ok: comment in extended mode
        preg_match('/(?m)^a$/', $value); // ok: inline multiline
        preg_match('/(?i-m)^a$/D', $value); // ok: D
    }
}
