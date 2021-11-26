<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

/**
 * @internal only for use by the app-system
 */
class NotificationResponse extends ActionButtonResponse
{
    public const ACTION_TYPE = 'notification';

    /**
     * One of the possible action statuses of notification.
     * Usually, this is one of: success, error, info, warning
     * According to these statuses, we could determine the type of notification
     */
    protected string $status;

    /**
     * This message is the content of the notification.
     */
    protected string $message;

    public function __construct()
    {
        parent::__construct(self::ACTION_TYPE);
    }
}
