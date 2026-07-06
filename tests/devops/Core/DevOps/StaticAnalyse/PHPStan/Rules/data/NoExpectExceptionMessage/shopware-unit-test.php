<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Foo;

use PHPUnit\Framework\TestCase;

class BarTest extends TestCase
{
    public function testFlagged(): void
    {
        $this->expectException(\RuntimeException::class);
        // not allowed — instance method call form
        $this->expectExceptionMessage('boom');

        throw new \RuntimeException('boom');
    }

    public function testFlaggedStatic(): void
    {
        static::expectException(\RuntimeException::class);
        // not allowed — static::expectExceptionMessage form
        static::expectExceptionMessage('boom');

        throw new \RuntimeException('boom');
    }

    public function testFlaggedSelf(): void
    {
        self::expectException(\RuntimeException::class);
        // not allowed — self::expectExceptionMessage form
        self::expectExceptionMessage('boom');

        throw new \RuntimeException('boom');
    }

    public function testFlaggedParent(): void
    {
        parent::expectException(\RuntimeException::class);
        // not allowed — parent::expectExceptionMessage form (resolves to TestCase)
        parent::expectExceptionMessage('boom');

        throw new \RuntimeException('boom');
    }

    public function testAllowed(): void
    {
        // allowed
        $this->expectExceptionObject(new \RuntimeException('boom'));

        throw new \RuntimeException('boom');
    }
}

class NotATestCase
{
    public function expectExceptionMessage(string $msg): void
    {
        // unrelated method on a non-TestCase class — not flagged because the
        // rule only inspects calls inside a TestCase subclass.
    }

    public function trigger(self $other): void
    {
        $other->expectExceptionMessage('not flagged');
    }
}
