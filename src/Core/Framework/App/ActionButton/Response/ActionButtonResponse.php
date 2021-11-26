<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
abstract class ActionButtonResponse extends Struct
{
    protected string $actionType;

    public function __construct(string $actionType)
    {
        $this->actionType = $actionType;
    }
}
