<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

/**
 * @script-service data_loading
 *
 * @internal
 */
class InternalService2
{
    public function foo(): string
    {
        return 'bar';
    }
}
