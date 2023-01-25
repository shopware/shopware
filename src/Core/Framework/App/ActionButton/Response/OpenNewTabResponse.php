<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class OpenNewTabResponse extends ActionButtonResponse
{
    final public const ACTION_TYPE = 'openNewTab';

    /**
     * This is the URL the user is redirected to after the action has been taken.
     */
    protected string $redirectUrl = '';

    public function __construct()
    {
        parent::__construct(self::ACTION_TYPE);
    }
}
