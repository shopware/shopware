<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Checkers;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Struct\ValidationResult;

#[Package('system-settings')]
interface CheckerInterface
{
    public const VALIDATION_SUCCESS = true;
    public const VALIDATION_ERROR = false;

    public function supports(string $check): bool;

    public function check(int|string|array $values): ValidationResult;
}
