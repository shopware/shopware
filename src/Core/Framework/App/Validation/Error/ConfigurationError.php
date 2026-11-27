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
class ConfigurationError implements Error
{
    /**
     * @param list<string> $violations
     */
    public function __construct(
        private readonly array $violations,
        private readonly string $appName
    ) {
    }

    public function getMessage(): string
    {
        return \sprintf('Custom components not allowed in app configuration: %s', implode(', ', $this->violations));
    }

    public function getErrorCode(): string
    {
        return AppException::INVALID_CONFIGURATION;
    }

    public function getParameters(): array
    {
        return ['appName' => $this->appName, 'error' => $this->getMessage()];
    }

    public function getSeverity(): Severity
    {
        return Severity::Fail;
    }
}
