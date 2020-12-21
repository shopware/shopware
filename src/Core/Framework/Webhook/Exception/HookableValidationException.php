<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Exception;

class HookableValidationException extends \RuntimeException
{
    public function __construct(string $appName, array $notHookableWebhooks, array $missingPermissions)
    {
        $message = $appName . ":\n";

        if (!empty($notHookableWebhooks)) {
            $message .= sprintf(
                "The following webhooks are not hookable:\n- %s",
                implode("\n- ", $notHookableWebhooks)
            );
        }

        if (!empty($missingPermissions)) {
            if (!empty($notHookableWebhooks)) {
                $message .= "\n\n";
            }

            $message .= sprintf(
                "The following permissions are missing:\n- %s",
                implode("\n- ", $missingPermissions)
            );
        }

        parent::__construct($message);
    }
}
