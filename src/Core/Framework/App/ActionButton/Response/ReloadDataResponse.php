<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

/**
 * @internal only for use by the app-system
 */
class ReloadDataResponse extends ActionButtonResponse
{
    public const ACTION_TYPE = 'reload';

    public function __construct()
    {
        parent::__construct(self::ACTION_TYPE);
    }
}
