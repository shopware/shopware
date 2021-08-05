<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\App\Exception\ActionProcessException;

/**
 * @internal only for use by the app-system
 */
class OpenNewTabResponse extends ActionButtonResponse
{
    /**
     * This is the URL the user is redirected to after the action has been taken.
     */
    public string $redirectUrl = '';

    public function validate(string $actionId): void
    {
        if (empty($this->redirectUrl)) {
            throw new ActionProcessException($actionId, 'Invalid redirect url provided by App');
        }
    }
}
