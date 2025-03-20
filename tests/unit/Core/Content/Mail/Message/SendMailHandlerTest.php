<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Message;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Message\SendMailHandler;
use Shopware\Core\Content\Mail\Message\SendMailMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(SendMailHandler::class)]
class SendMailHandlerTest extends TestCase
{
    private MockObject&TransportInterface $transport;

    private MockObject&FilesystemOperator $fileSystem;

    private MockObject&LoggerInterface $logger;

    private SendMailHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = $this->createMock(TransportInterface::class);
        $this->fileSystem = $this->createMock(FilesystemOperator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SendMailHandler($this->transport, $this->fileSystem, $this->logger);
    }

    public function testHandle(): void
    {
        $mail = new Email();

        $message = new SendMailMessage('mail-data/test');

        $this->fileSystem->expects($this->once())
            ->method('read')
            ->with('mail-data/test')
            ->willReturn(serialize($mail));

        $this->transport->expects($this->once())
            ->method('send')
            ->with($mail);

        $this->fileSystem->expects($this->once())
            ->method('delete')
            ->with('mail-data/test');

        $this->handler->__invoke($message);
    }

    public function testHandleFileReadException(): void
    {
        $message = new SendMailMessage('mail-data/test');

        $this->fileSystem->expects($this->once())
            ->method('read')
            ->with('mail-data/test')
            ->willThrowException(new UnableToReadFile());

        $this->fileSystem->expects($this->once())
            ->method('fileExists')
            ->with('mail-data/test')
            ->willReturn(true);

        $this->expectException(FilesystemException::class);
        $this->handler->__invoke($message);
    }

    public function testHandleFileDoesNotExistException(): void
    {
        $message = new SendMailMessage('mail-data/test');

        $this->fileSystem->expects($this->once())
            ->method('read')
            ->with('mail-data/test')
            ->willThrowException(new UnableToReadFile());

        $this->fileSystem->expects($this->once())
            ->method('fileExists')
            ->with('mail-data/test')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('The mail data file does not exist. Mail could not be sent.', ['mailDataPath' => 'mail-data/test', 'exception' => '']);

        $this->handler->__invoke($message);
    }

    public function testHandleInvalidMailData(): void
    {
        $message = new SendMailMessage('mail-data/test');

        $this->fileSystem->expects($this->once())
            ->method('read')
            ->with('mail-data/test')
            ->willReturn(serialize('invalid-data'));

        $this->fileSystem->expects($this->never())->method('delete');
        $this->logger->expects($this->once())
            ->method('error')
            ->with('The mail data file does not contain a valid email object. Mail could not be sent.', ['mailDataPath' => 'mail-data/test']);

        $this->transport->expects($this->never())->method('send');

        $this->handler->__invoke($message);
    }

    public function testHandleInvalidMailDataDeleteException(): void
    {
        $message = new SendMailMessage('mail-data/test');

        $this->fileSystem->expects($this->once())
            ->method('read')
            ->with('mail-data/test')
            ->willReturn(serialize('invalid-data'));

        $this->fileSystem->expects($this->never())->method('delete');
        $this->logger->expects($this->once())
            ->method('error')
            ->with('The mail data file does not contain a valid email object. Mail could not be sent.', ['mailDataPath' => 'mail-data/test']);

        $this->handler->__invoke($message);
    }

    public function testHandleDeleteException(): void
    {
        $mail = new Email();

        $message = new SendMailMessage('mail-data/test');

        $this->fileSystem->expects($this->once())
            ->method('read')
            ->with('mail-data/test')
            ->willReturn(serialize($mail));

        $this->transport->expects($this->once())
            ->method('send')
            ->with($mail);

        $this->fileSystem->expects($this->once())
            ->method('delete')
            ->with('mail-data/test')
            ->willThrowException(new UnableToDeleteFile());

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Could not delete mail data file after sending mail.', ['mailDataPath' => 'mail-data/test', 'exception' => '']);

        $this->handler->__invoke($message);
    }
}
