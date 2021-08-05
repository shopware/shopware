<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\App\Exception\ActionProcessException;

/**
 * @internal only for use by the app-system
 */
class ActionButtonResponseFactory
{
    public function createFromResponse(string $actionId, string $actionType, array $payload): ActionButtonResponse
    {
        switch ($actionType) {
            case ActionButtonResponse::ACTION_SHOW_NOTITFICATION:
                return NotificationResponse::create($actionId, $actionType, $payload);
            case ActionButtonResponse::ACTION_OPEN_NEW_TAB:
                return OpenNewTabResponse::create($actionId, $actionType, $payload);
            case ActionButtonResponse::ACTION_RELOAD_DATA:
                return ReloadDataResponse::create($actionId, $actionType, $payload);
            case ActionButtonResponse::ACTION_OPEN_MODAL:
                return OpenModalResponse::create($actionId, $actionType, $payload);
            default:
                throw new ActionProcessException($actionId, 'Invalid action type provided by app');
        }
    }
}
