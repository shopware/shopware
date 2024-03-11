<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;

/**
 * @internal
 */
#[CoversClass(MessageQueueException::class)]
class MessageQueueExceptionTest extends TestCase
{
    public function testValidReceiverNameNotProvided(): void
    {
        $exception = MessageQueueException::validReceiverNameNotProvided();

        static::assertSame('FRAMEWORK__NO_VALID_RECEIVER_NAME_PROVIDED', $exception->getErrorCode());
        static::assertSame('No receiver name provided.', $exception->getMessage());
        static::assertSame(400, $exception->getStatusCode());
    }

    public function testWorkerIsLocked(): void
    {
        $exception = MessageQueueException::workerIsLocked('test');

        static::assertSame('FRAMEWORK__WORKER_IS_LOCKED', $exception->getErrorCode());
        static::assertSame('Another worker is already running for receiver: "test"', $exception->getMessage());
        static::assertSame(409, $exception->getStatusCode());
    }
}
