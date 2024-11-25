<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Message;

use Composer\Downloader\FilesystemException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Message\SendMailHandler;
use Shopware\Core\Content\Mail\Message\SendMailMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(SendMailHandler::class)]
class SendMailHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $handler = new SendMailHandler($transport, $fileSystem);

        $mail = new Email();

        $message = new SendMailMessage('mail-data/test');

        $fileSystem->expects(static::once())
            ->method('read')
            ->with('mail-data/test')
            ->willReturn(serialize($mail));

        $transport->expects(static::once())
            ->method('send')
            ->with($mail);

        $fileSystem->expects(static::once())
            ->method('delete')
            ->with('mail-data/test');

        $handler->__invoke($message);
    }

    public function testHandleFileReadException(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $handler = new SendMailHandler($transport, $fileSystem);

        $message = new SendMailMessage('mail-data/test');

        $fileSystem->expects(static::once())
            ->method('read')
            ->with('mail-data/test')
            ->willThrowException(new FilesystemException());

        $fileSystem->expects(static::once())
            ->method('fileExists')
            ->with('mail-data/test')
            ->willReturn(true);

        $this->expectException(FilesystemException::class);

        $handler->__invoke($message);
    }

    public function testHandleFileDoesNotExistException(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $handler = new SendMailHandler($transport, $fileSystem);

        $message = new SendMailMessage('mail-data/test');

        $fileSystem->expects(static::once())
            ->method('read')
            ->with('mail-data/test')
            ->willThrowException(new FilesystemException());

        $fileSystem->expects(static::once())
            ->method('fileExists')
            ->with('mail-data/test')
            ->willReturn(false);

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler->__invoke($message);
    }

    public function testHandleInvalidMailData(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $handler = new SendMailHandler($transport, $fileSystem);

        $message = new SendMailMessage('mail-data/test');

        $fileSystem->expects(static::once())
            ->method('read')
            ->with('mail-data/test')
            ->willReturn('invalid-data');

        $fileSystem->expects(static::once())
            ->method('delete')
            ->with('mail-data/test');

        $handler->__invoke($message);
    }

    public function testHandleInvalidMailDataDeleteException(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $handler = new SendMailHandler($transport, $fileSystem);

        $message = new SendMailMessage('mail-data/test');

        $fileSystem->expects(static::once())
            ->method('read')
            ->with('mail-data/test')
            ->willReturn('invalid-data');

        $fileSystem->expects(static::once())
            ->method('delete')
            ->with('mail-data/test')
            ->willThrowException(new FilesystemException());

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler->__invoke($message);
    }

    public function testHandleDeleteException(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $handler = new SendMailHandler($transport, $fileSystem);

        $mail = new Email();

        $message = new SendMailMessage('mail-data/test');

        $fileSystem->expects(static::once())
            ->method('read')
            ->with('mail-data/test')
            ->willReturn(serialize($mail));

        $transport->expects(static::once())
            ->method('send')
            ->with($mail);

        $fileSystem->expects(static::once())
            ->method('delete')
            ->with('mail-data/test')
            ->willThrowException(new FilesystemException());

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler->__invoke($message);
    }
}
