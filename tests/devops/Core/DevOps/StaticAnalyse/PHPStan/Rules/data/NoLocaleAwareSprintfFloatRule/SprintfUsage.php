<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoLocaleAwareSprintfFloatRule;

class SprintfUsage
{
    public function invalid(float $value, string $format): void
    {
        // Basic specifier
        \sprintf('%f', $value);

        // Flags: left alignment, explicit sign, leading space, zero padding, custom padding
        \sprintf('%-10.2f', $value);
        \sprintf('%+10.2f', $value);
        \sprintf('% 10.2f', $value);
        \sprintf('%010.2f', $value);
        \sprintf('%\'_10.2f', $value);

        // Argument number, precision, and a literal percent before the conversion
        \sprintf('%2$.1f', $value, $value);
        \sprintf('%%%f', $value);

        // Locale-independent, escaped, and dynamic formats remain valid
        \sprintf('%F', $value);
        \sprintf('%%f', $value);
        \sprintf($format, $value);
    }
}
