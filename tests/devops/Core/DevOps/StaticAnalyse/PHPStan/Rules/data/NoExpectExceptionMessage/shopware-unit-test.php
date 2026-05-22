<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Foo;

use PHPUnit\Framework\TestCase;

class BarTest extends TestCase
{
    public function testFlagged(): void
    {
        $this->expectException(\RuntimeException::class);
        // not allowed
        $this->expectExceptionMessage('boom');

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
