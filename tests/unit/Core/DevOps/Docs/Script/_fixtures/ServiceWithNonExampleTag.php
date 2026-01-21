<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

/**
 * @script-service data_loading
 */
class ServiceWithNonExampleTag
{
    /**
     * @example
     *
     * @return string desc
     */
    public function foo(): string
    {
        return '';
    }
}
