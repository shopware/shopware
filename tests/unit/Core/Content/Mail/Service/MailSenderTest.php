<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\Content\Mail\Message\SendMailMessage;
use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\TextPart;

/**
 * @internal
 */
#[CoversClass(MailSender::class)]
class MailSenderTest extends TestCase
{
    public function testSendMail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender($mailer, $fileSystem, $configService, 0, $messageBus);
        $mail = new Email();

        $mailer
            ->expects(static::once())
            ->method('send')
            ->with($mail);

        $mailSender->send($mail);
    }

    public function testSendMailWithoutMessageBus(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender($mailer, $fileSystem, $configService, 0, null);
        $mail = new Email();

        $mailer
            ->expects(static::once())
            ->method('send')
            ->with($mail);

        $mailSender->send($mail);
    }

    public function testSendLargeMail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender($mailer, $fileSystem, $configService, 0, $messageBus);
        $text = str_repeat('a', MailSender::MAIL_MESSAGE_SIZE_LIMIT);
        $mail = new Email(null, new TextPart($text));

        $testStruct = new ArrayStruct();

        $fileSystem
            ->expects(static::once())
            ->method('write')
            ->willReturnCallback(function ($path, $content) use ($mail, $testStruct): void {
                static::assertStringStartsWith('mail-data/', $path);
                static::assertSame(serialize($mail), $content);
                $testStruct->set('mailDataPath', $path);
            });

        $messageBus
            ->expects(static::once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) use ($testStruct): Envelope {
                static::assertInstanceOf(SendMailMessage::class, $message);
                static::assertSame($testStruct->get('mailDataPath'), $message->mailDataPath);

                return new Envelope($message);
            });

        $mailSender->send($mail);
    }

    public function testSendMailWithDisabledDelivery(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(true);
        $mailSender = new MailSender($mailer, $fileSystem, $configService, 0, $messageBus);
        $mail = new Email();

        $fileSystem
            ->expects(static::never())
            ->method('write');

        $messageBus
            ->expects(static::never())
            ->method('dispatch');

        $mailSender->send($mail);
    }

    public function testSendMailWithToMuchContent(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender($mailer, $fileSystem, $configService, 5, $messageBus);

        $mail = new Email();
        $mail->text('foobar');

        static::expectException(MailException::class);
        static::expectExceptionMessage('Mail body is too long. Maximum allowed length is 5');

        $mailSender->send($mail);
    }
}
