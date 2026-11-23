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

        // PHPStan can resolve this variable to the constant string "%f".
        $example = '%f';
        \sprintf($example, $value);

        // Locale-independent and escaped formats are valid.
        \sprintf('%F', $value);
        \sprintf('%%f', $value);

        // PHPStan cannot determine the runtime value of this format string, so this rule does not inspect it.
        \sprintf($format, $value);
    }
}
