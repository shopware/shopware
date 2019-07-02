<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Checkers;

use Shopware\Core\Framework\Update\Struct\ValidationResult;

interface CheckerInterface
{
    public const VALIDATION_SUCCESS = 1;
    public const VALIDATION_ERROR = 0;

    public function supports(string $check): bool;

    /**
     * @param int|string|array $values
     */
    public function check($values): ValidationResult;
}
