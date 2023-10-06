<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;

/**
 * @internal
 */
class StoppableTestHook extends TestHook implements StoppableHook
{
    use StoppableHookTrait;
}
