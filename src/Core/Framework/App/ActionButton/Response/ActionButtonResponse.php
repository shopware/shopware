<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 *
 * @package core
 */
abstract class ActionButtonResponse extends Struct
{
    public function __construct(protected string $actionType)
    {
    }
}
