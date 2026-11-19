<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WebhookNotPermittedError implements Error
{
    public const CODE = 'FRAMEWORK__APP_WEBHOOK_NOT_PERMITTED';

    private readonly string $message;

    /**
     * @param list<string> $violations
     */
    public function __construct(array $violations)
    {
        $this->message = \sprintf(
            "This app is not permitted to subscribe to the following webhooks:\n- %s",
            implode("\n- ", $violations)
        );
    }

    public function getMessage(): string
    {
        return $this->message;
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
