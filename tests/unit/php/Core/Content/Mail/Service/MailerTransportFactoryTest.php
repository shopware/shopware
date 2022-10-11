<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailerTransportFactory;
use Shopware\Tests\Unit\Common\Stubs\SystemConfigService\ConfigService;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\NullTransportFactory;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Mail\Service\MailerTransportFactory
 */
class MailerTransportFactoryTest extends TestCase
{
    public function testUseSymfonyDefaultDSN(): void
    {
        $factory = new MailerTransportFactory(
            $this->getFactories(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => '',
            ])
        );

        $transport = $factory->fromString('smtp://localhost:25');

        static::assertInstanceOf(EsmtpTransport::class, $transport);

        $transport = $factory->fromString('sendmail://default');

        static::assertInstanceOf(SendmailTransport::class, $transport);

        // Create returns default Sendmail
        static::assertInstanceOf(SendmailTransport::class, $factory->create());
    }

    public function testFactoryWithoutConfig(): void
    {
        $original = new EsmtpTransport();

        $factory = new MailerTransportFactory(
            $this->getFactories(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => null,
            ])
        );

        $mailer = $factory->fromString('smtp://example.com:1025');

        static::assertEquals(\get_class($original), \get_class($mailer));
    }

    public function testFactoryWithLocal(): void
    {
        $original = new SendmailTransport();

        $factory = new MailerTransportFactory(
            $this->getFactories(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => null,
            ])
        );

        $mailer = $factory->fromString('null://null');

        static::assertEquals(\get_class($original), \get_class($mailer));
    }

    /**
     * @dataProvider providerEncryption
     */
    public function testFactoryWithConfig(?string $encryption): void
    {
        $original = new EsmtpTransport();

        $factory = new MailerTransportFactory(
            $this->getFactories(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'smtp',
                'core.mailerSettings.host' => 'localhost',
                'core.mailerSettings.port' => '225',
                'core.mailerSettings.username' => 'root',
                'core.mailerSettings.password' => 'root',
                'core.mailerSettings.encryption' => $encryption,
                'core.mailerSettings.authenticationMethod' => 'cram-md5',
            ])
        );

        /** @var EsmtpTransport $mailer */
        $mailer = $factory->fromString('null://null');

        static::assertEquals(\get_class($original), \get_class($mailer));
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public function providerEncryption(): iterable
    {
        yield 'tls' => ['tls'];
        yield 'ssl' => ['ssl'];
        yield 'null' => [null];
    }

    public function testFactoryWithLocalAndInvalidConfig(): void
    {
        $factory = new MailerTransportFactory(
            $this->getFactories(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => '-t && echo bla',
            ])
        );

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Given sendmail option "-t && echo bla" is invalid');

        $factory->fromString('null://null');
    }

    public function testFactoryInvalidAgent(): void
    {
        $factory = new MailerTransportFactory(
            $this->getFactories(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'test',
            ])
        );

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Invalid mail agent given "test"');

        $factory->fromString('null://null');
    }

    /**
     * @return array<string, AbstractTransportFactory>
     */
    public function getFactories(): array
    {
        return [
            'smtp' => new EsmtpTransportFactory(),
            'sendmail' => new SendmailTransportFactory(),
            'null' => new NullTransportFactory(),
        ];
    }
}
