<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
abstract class ActionButtonResponse extends Struct
{
    public function __construct(protected string $actionType)
    {
    }
}
