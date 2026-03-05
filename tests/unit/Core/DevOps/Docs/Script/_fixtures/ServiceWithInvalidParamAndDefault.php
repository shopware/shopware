<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

/**
 * @script-service data_loading
 */
class ServiceWithInvalidParamAndDefault
{
    /**
     * @param array[0] $bar offset access type forces InvalidTag with name 'param'
     *
     * @return string desc
     */
    public function foo($bar = 'default'): string
    {
        return $bar;
    }
}
