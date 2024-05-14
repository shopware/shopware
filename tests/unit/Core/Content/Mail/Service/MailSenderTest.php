<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(MailSender::class)]
class MailSenderTest extends TestCase
{
    public function testSendMail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender($mailer, $configService, 0);
        $mail = new Email();

        $mailer->expects(static::once())->method('send')->with($mail);

        $mailSender->send($mail);
    }

    public function testSendMailWithDisabledDelivery(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(true);
        $mailSender = new MailSender($mailer, $configService, 0);
        $mail = new Email();

        $mailer->expects(static::never())->method('send');

        $mailSender->send($mail);
    }

    public function testSendMailWithDeliveryAddress(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $configService->method('getString')->with('core.mailerSettings.deliveryAddress')->willReturn('test@example.com');
        $mailSender = new MailSender($mailer, $configService, 0);
        $mail = new Email();

        $mailer->expects(static::once())->method('send');

        $mailSender->send($mail);

        $bcc = $mail->getBcc();
        static::assertCount(1, $bcc);

        $address = $bcc[0]->getAddress();
        static::assertEquals($address, 'test@example.com');
    }

    public function testSendMailWithToMuchContent(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender($mailer, $configService, 5);

        $mail = new Email();
        $mail->text('foobar');

        static::expectException(MailException::class);
        static::expectExceptionMessage('Mail body is too long. Maximum allowed length is 5');

        $mailSender->send($mail);
    }

    public function testSendMailerThrowsException(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \Exception('test'));

        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender($mailer, $configService, 0);

        $mail = new Email();

        static::expectException(MailTransportFailedException::class);

        $mailSender->send($mail);
    }
}
