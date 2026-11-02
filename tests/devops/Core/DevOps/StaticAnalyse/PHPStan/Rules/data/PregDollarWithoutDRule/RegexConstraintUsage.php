<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PregDollarWithoutDRule;

use Symfony\Component\Validator\Constraints\Regex;

class RegexConstraintUsage
{
    private const TIME = '/^\d{2}$/';

    public function constraints(string $runtime): void
    {
        // flagged and accepted constraints
        new Regex('/^[a-z]+$/'); // flagged: positional pattern
        new Regex(pattern: '/^[a-z]+$/', message: 'invalid'); // flagged: named pattern
        new Regex(pattern: self::TIME); // flagged: class constant
        new Regex(['pattern' => '/^[A-Z][a-z]*$/']); // flagged: legacy options array
        new Regex('/^[a-z]+$/D'); // ok: D
        new Regex(pattern: '/^[a-z]+$/', normalizer: 'trim'); // ok: the normalizer strips the newline
        new Regex('/https?:/'); // ok: not anchored
        new \DateTimeImmutable('now'); // ok: not a constraint
        new Regex($runtime); // unresolved
    }
}
