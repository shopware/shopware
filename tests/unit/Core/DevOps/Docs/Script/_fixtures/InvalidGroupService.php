<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

/**
 * @script-service invalid_group
 */
class InvalidGroupService
{
    public function foo(): string
    {
        return 'bar';
    }
}
