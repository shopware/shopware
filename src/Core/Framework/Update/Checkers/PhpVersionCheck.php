<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Checkers;

use Shopware\Core\Framework\Update\Struct\ValidationResult;

class PhpVersionCheck implements CheckerInterface
{
    public function supports(string $check): bool
    {
        return $check === 'phpversion';
    }

    /**
     * @param int|string|array $values
     */
    public function check($values): ValidationResult
    {
        $vars = ['minVersion' => $values, 'currentVersion' => \PHP_VERSION];

        if (version_compare(\PHP_VERSION, $values, '>=')) {
            return new ValidationResult('phpVersion', self::VALIDATION_SUCCESS, 'phpVersion', $vars);
        }

        return new ValidationResult('phpVersion', self::VALIDATION_ERROR, 'phpVersion', $vars);
    }
}
