<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\fixtures;

use Shopware\Core\Framework\DependencyInjection\CompilerPass\ActionEventCompilerPass;

class TestActionEventCompilerPass extends ActionEventCompilerPass
{
    protected function getReflectionClass(): \ReflectionClass
    {
        return new \ReflectionClass(TestBusinessEvents::class);
    }
}
