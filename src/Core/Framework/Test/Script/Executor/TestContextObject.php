<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Executor;

class TestContextObject
{
    private bool $called = false;

    public function setter(): void
    {
        $this->called = true;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }
}
