<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailerTransportLoader;
use Shopware\Tests\Unit\Common\Stubs\SystemConfigService\ConfigService;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Mail\Service\MailerTransportLoader
 */
class MailerTransportLoaderTest extends TestCase
{
    public function testUseSymfonyTransportDefault(): void
    {
        $transport = $this->getTransportFactory();
        $transport->expects(static::atLeast(1))
            ->method('fromString');

        $loader = new MailerTransportLoader(
            $transport,
            new ConfigService([
                'core.mailerSettings.emailAgent' => '',
            ])
        );

        $loader->fromString('smtp://localhost:25');
    }

    public function testFactoryWithLocal(): void
    {
        $factory = new MailerTransportLoader(
            $this->getTransportFactory(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => null,
            ])
        );

        $mailer = $factory->fromString('null://null');

        static::assertInstanceOf(SendmailTransport::class, $mailer);
    }

    /**
     * @dataProvider providerSmtpEncryption
     */
    public function testLoaderWithSmtpConfig(?string $encryption): void
    {
        $transport = $this->getTransportFactory();
        $transport->expects(static::atLeast(1))
            ->method('fromDsnObject');

        $loader = new MailerTransportLoader(
            $transport,
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
        $mailer = $loader->fromString('null://null');

        static::assertInstanceOf(EsmtpTransport::class, $mailer);
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public function providerSmtpEncryption(): iterable
    {
        yield 'tls' => ['tls'];
        yield 'ssl' => ['ssl'];
        yield 'null' => [null];
    }

    public function testFactoryWithLocalAndInvalidConfig(): void
    {
        $loader = new MailerTransportLoader(
            $this->getTransportFactory(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => '-t && echo bla',
            ])
        );

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Given sendmail option "-t && echo bla" is invalid');

        $loader->fromString('null://null');
    }

    public function testFactoryInvalidAgent(): void
    {
        $loader = new MailerTransportLoader(
            $this->getTransportFactory(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'test',
            ])
        );

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Invalid mail agent given "test"');

        $loader->fromString('null://null');
    }

    /**
     * @return array<string, AbstractTransportFactory>
     */
    private function getFactories(): array
    {
        $smtpTransport = $this->createPartialMock(EsmtpTransportFactory::class, ['create']);
        $smtpTransport->expects(static::any())
            ->method('create')
            ->willReturn($this->createMock(EsmtpTransport::class));

        return [
            'smtp' => $smtpTransport,
        ];
    }

    /**
     * @return mixed can't annotate more specific as phpstan does not allow to annotate as MockObject&Transport as Transport is final
     */
    private function getTransportFactory(): mixed
    {
        $factories = $this->getFactories();

        $transport = $this
            ->getMockBuilder(Transport::class)
            ->setConstructorArgs([$factories, 'null://null'])
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();

        $transport->expects(static::any())
            ->method('fromDsnObject')
            ->willReturnCallback(function (Dsn $dsn) use ($factories) {
                foreach ($factories as $factory) {
                    if ($factory->supports($dsn)) {
                        return $factory->create($dsn);
                    }
                }

                return null;
            });

        return $transport;
    }
}
