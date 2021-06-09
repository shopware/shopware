<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\App\Exception\ActionProcessException;

/**
 * @internal only for use by the app-system
 */
class NotificationResponse extends ActionButtonResponse
{
    /**
     * One of the possible action statuses of notification.
     * Usually, this is one of: success, error, info, warning
     * According to these statuses, we could determine the type of notification
     */
    public string $status;

    /**
     * This message is the content of the notification.
     */
    public string $message;

    public function validate(string $actionId): void
    {
        if (empty($this->status) || empty($this->message)) {
            throw new ActionProcessException($actionId, 'Invalid status or message provided by App');
        }
    }
}
