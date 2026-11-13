<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\App\Validation\HookableValidatorTest
 */
#[Package('framework')]
class NotHookableError implements Error
{
    /**
     * @param array<string, string> $webhooks
     */
    public function __construct(private readonly array $webhooks)
    {
    }

    public function getMessage(): string
    {
        $webhooks = [];
        foreach ($this->webhooks as $name => $event) {
            $webhooks[] = \sprintf('%s (%s)', $name, $event);
        }

        return \sprintf('Webhooks not hookable: %s', implode(', ', $webhooks));
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
        return Severity::Notice;
    }
}
