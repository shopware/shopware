<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

/**
 * @script-service data_loading
 */
class ServiceWithInvalidParamDocBlock
{
    /**
     * @param string $foo This is a valid param
     * @param mixed $bar This is an invalid param
     */
    public function methodWithInvalidParam($foo, $bar): void
    {
    }
}
