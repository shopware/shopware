<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteContext::class)]
class WriteContextTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    public function testWriteErrorsAreDispatchedImmediatelyByDefault(): void
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $context->onWriteError(function (): void {
            $this->calls[] = 'immediate';
        });

        static::assertSame(['immediate'], $this->calls);
        $context->dispatchWriteErrors();
        static::assertSame(['immediate'], $this->calls);
    }

    public function testAnOuterTransactionCanDeferErrorCallbacksUntilItsFinalFailure(): void
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $context->addState(WriteContext::STATE_DEFER_ERROR_CALLBACKS);
        $context->onWriteError(function (): void {
            $this->calls[] = 'first';
        });
        $context->onWriteError(function (): void {
            $this->calls[] = 'second';
        });

        static::assertSame([], $this->calls);
        $context->dispatchWriteErrors();
        static::assertSame(['first', 'second'], $this->calls);
        $context->dispatchWriteErrors();
        static::assertSame(['first', 'second'], $this->calls);
    }
}
