<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ConfigurationError extends Error
{
    private const KEY = 'manifest-invalid-config';

    /**
     * @param list<string> $violations
     */
    public function __construct(array $violations, private readonly string $appName)
    {
        $this->message = \sprintf(
            "The following custom components are not allowed to be used in app configuration:\n- %s",
            implode("\n- ", $violations)
        );

        parent::__construct($this->message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getErrorCode(): string
    {
        return AppException::INVALID_CONFIGURATION;
    }

    public function getParameters(): array
    {
        return ['appName' => $this->appName, 'error' => $this->getMessage()];
    }
}
