<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

/**
 * @script-service data_loading
 */
class ServiceWithScriptServiceReturnType
{
    /**
     * @return InjectedService desc
     */
    public function foo(): InjectedService
    {
        throw new \LogicException('not implemented');
    }
}
