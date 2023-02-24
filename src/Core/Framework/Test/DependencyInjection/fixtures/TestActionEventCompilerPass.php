<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\fixtures;

use Shopware\Core\Framework\DependencyInjection\CompilerPass\ActionEventCompilerPass;

/**
 * @internal
 */
class TestActionEventCompilerPass extends ActionEventCompilerPass
{
    /**
     * @phpstan-ignore-next-line return type is overwritten, because a test event class is used
     *
     * @return \ReflectionClass<TestBusinessEvents>
     */
    protected function getReflectionClass(): \ReflectionClass
    {
        return new \ReflectionClass(TestBusinessEvents::class);
    }
}
