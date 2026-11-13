<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class MissingPermissionError implements Error
{
    /**
     * @param list<string> $violations
     */
    public function __construct(private readonly array $violations)
    {
    }

    public function getMessage(): string
    {
        return \sprintf('Missing permissions: %s', implode(', ', $this->violations));
    }

    public function getErrorCode(): string
    {
        return AppException::VALIDATION_FAILED;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function getSeverity(): Severity
    {
        return Severity::Fail;
    }
}
