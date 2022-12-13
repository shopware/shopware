<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Mail\Service\MailerTransportDecorator;
use Shopware\Core\Content\Mail\Service\MailerTransportLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
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
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
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
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
        );

        $mailer = $factory->fromString('null://null');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        $decorated = ReflectionHelper::getPropertyValue($mailer, 'decorated');

        static::assertInstanceOf(SendmailTransport::class, $decorated);
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
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
        );

        $mailer = $loader->fromString('null://null');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        $decorated = ReflectionHelper::getPropertyValue($mailer, 'decorated');

        static::assertInstanceOf(EsmtpTransport::class, $decorated);
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
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
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
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
        );

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Invalid mail agent given "test"');

        $loader->fromString('null://null');
    }

    /**
     * @return array<string, AbstractTransportFactory>
     */
    public function getFactories(): array
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
     * @return Transport|MockObject
     */
    public function getTransportFactory(): Transport
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
