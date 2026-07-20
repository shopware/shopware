<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

/**
 * @script-service data_loading
 *
 * @description ValidService3 for testing skipping __construct and methods
 */
class ValidService3
{
    /**
     * Constructor for coverage
     */
    public function __construct()
    {
    }

    /**
     * @internal
     */
    public function internalMethod(): void
    {
    }

    /**
     * @return void desc
     */
    public function foo(): void
    {
    }
}
