<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class MissingPermissionError implements Error
{
    public const CODE = 'FRAMEWORK__APP_MISSING_PERMISSIONS';

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
        return self::CODE;
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
