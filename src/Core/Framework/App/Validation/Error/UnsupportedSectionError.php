<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class UnsupportedSectionError implements Error
{
    public function __construct(private readonly string $section)
    {
    }

    public function getMessage(): string
    {
        return \sprintf('Section <%s> is not supported by this Shopware version', $this->section);
    }

    public function getErrorCode(): string
    {
        return AppException::VALIDATION_FAILED;
    }

    public function getParameters(): array
    {
        return ['section' => $this->section];
    }

    public function getSeverity(): Severity
    {
        return Severity::Notice;
    }
}
