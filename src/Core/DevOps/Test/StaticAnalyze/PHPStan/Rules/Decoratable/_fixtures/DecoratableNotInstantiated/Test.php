<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable\_fixtures\DecoratableNotInstantiated;

class Test
{
    public function test(): void
    {
        $_decoratable = new DecoratableClass();
        $_notTagged = new NotTaggedClass();
    }
}
