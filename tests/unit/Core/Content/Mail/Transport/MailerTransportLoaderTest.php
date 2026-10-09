<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Transport;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Mail\Transport\MailerTransportDecorator;
use Shopware\Core\Content\Mail\Transport\MailerTransportLoader;
use Shopware\Core\Content\Mail\Transport\SmtpOauthAuthenticator;
use Shopware\Core\Content\Mail\Transport\SmtpOauthTransportFactoryDecorator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Doctrine\TestExceptionFactory;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\NullTransportFactory;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailerTransportLoader::class)]
class MailerTransportLoaderTest extends TestCase
{
    public function testUseSymfonyTransportDefault(): void
    {
        $transport = $this->getTransportFactory();

        $loader = new MailerTransportLoader(
            $transport,
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => '',
            ]),
            static::createStub(MailAttachmentsBuilder::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(EntityRepository::class)
        );

        $trans = $loader->fromString('smtp://localhost:25');

        static::assertInstanceOf(MailerTransportDecorator::class, $trans);

        // The decorator presents the wrapped transport's name.
        static::assertSame('smtp://localhost', (string) $trans);
    }

    public function testFactoryWithLocal(): void
    {
        $factory = new MailerTransportLoader(
            $this->getTransportFactory(),
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => null,
            ]),
            static::createStub(MailAttachmentsBuilder::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(EntityRepository::class)
        );

        $mailer = $factory->fromString('null://null');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        static::assertSame('smtp://sendmail', (string) $mailer);
    }

    #[DataProvider('providerSmtpEncryption')]
    public function testLoaderWithSmtpConfig(?string $encryption, string $expectedTransport): void
    {
        $transport = $this->getTransportFactory();

        $loader = new MailerTransportLoader(
            $transport,
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'smtp',
                'core.mailerSettings.host' => 'localhost',
                'core.mailerSettings.port' => '225',
                'core.mailerSettings.username' => 'root',
                'core.mailerSettings.password' => 'root',
                'core.mailerSettings.encryption' => $encryption,
                'core.mailerSettings.authenticationMethod' => 'cram-md5',
            ]),
            static::createStub(MailAttachmentsBuilder::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(EntityRepository::class)
        );

        $mailer = $loader->fromString('null://null');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        static::assertSame($expectedTransport, (string) $mailer);
    }

    public static function providerSmtpEncryption(): \Generator
    {
        yield 'tls' => ['tls', 'smtp://localhost:225'];
        yield 'ssl' => ['ssl', 'smtps://localhost:225'];
        yield 'null' => [null, 'smtp://localhost:225'];
    }

    public function testLoaderWithSmtpOauthConfig(): void
    {
        $transport = $this->getTransportFactory();

        $loader = new MailerTransportLoader(
            $transport,
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'smtp+oauth',
                'core.mailerSettings.host' => 'localhost',
                'core.mailerSettings.port' => '225',
                'core.mailerSettings.clientId' => '123',
                'core.mailerSettings.clientSecret' => 'SECRET',
                'core.mailerSettings.oauthUrl' => 'test',
                'core.mailerSettings.oauthScope' => 'test',
                'core.mailerSettings.senderAddress' => 'test@example.com',
                'core.mailerSettings.encryption' => 'tls',
            ]),
            static::createStub(MailAttachmentsBuilder::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(EntityRepository::class),
        );

        $mailer = $loader->fromString('null://null');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        static::assertSame('smtp://localhost:225', (string) $mailer);
    }

    public function testFactoryWithLocalAndInvalidConfig(): void
    {
        $loader = new MailerTransportLoader(
            $this->getTransportFactory(),
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => '-t bla',
            ]),
            static::createStub(MailAttachmentsBuilder::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(EntityRepository::class)
        );

        $this->expectExceptionObject(MailException::givenSendMailOptionIsInvalid('bla', ['-bs', '-i', '-t']));

        $loader->fromString('null://null');
    }

    public function testFactoryWithLocalAndValidConfig(): void
    {
        $loader = new MailerTransportLoader(
            $this->getTransportFactory(),
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => '-t    -i',
            ]),
            static::createStub(MailAttachmentsBuilder::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(EntityRepository::class)
        );

        $res = $loader->fromString('null://null');
        static::assertInstanceOf(MailerTransportDecorator::class, $res);
    }

    public function testFactoryInvalidAgent(): void
    {
        $loader = new MailerTransportLoader(
            $this->getTransportFactory(),
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'test',
            ]),
            static::createStub(MailAttachmentsBuilder::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(EntityRepository::class)
        );

        $this->expectExceptionObject(MailException::givenMailAgentIsInvalid('test'));

        $loader->fromString('null://null');
    }

    public function testFactoryNoConnection(): void
    {
        $config = static::createStub(SystemConfigService::class);
        $config->method('get')->willThrowException(TestExceptionFactory::createDriverException('no connection'));

        $loader = new MailerTransportLoader(
            $this->getTransportFactory(),
            $config,
            static::createStub(MailAttachmentsBuilder::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(EntityRepository::class)
        );

        $mailer = $loader->fromString('null://null');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        static::assertSame('null://', (string) $mailer);
    }

    public function testLoadMultipleMailers(): void
    {
        $requestedDsns = [];
        $factory = $this->createMock(TransportFactoryInterface::class);
        $factory->method('supports')->willReturn(true);
        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(static function (Dsn $dsn) use (&$requestedDsns): NullTransport {
                $requestedDsns[] = \sprintf('%s://%s:%s', $dsn->getScheme(), $dsn->getHost(), $dsn->getPort());

                return new NullTransport();
            });

        $loader = new MailerTransportLoader(
            new Transport([$factory]),
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'smtp',
                'core.mailerSettings.host' => 'localhost',
                'core.mailerSettings.port' => '225',
                'core.mailerSettings.username' => 'root',
                'core.mailerSettings.password' => 'root',
                'core.mailerSettings.encryption' => 'foo',
                'core.mailerSettings.authenticationMethod' => 'cram-md5',
            ]),
            static::createStub(MailAttachmentsBuilder::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(EntityRepository::class)
        );

        $dsns = [
            'main' => 'null://localhost:25',
            'fallback' => 'null://localhost:25',
        ];

        $transports = $loader->fromStrings($dsns);

        static::assertSame('[main,fallback]', (string) $transports);
        // Main is built from the system config, the fallback from its DSN.
        static::assertSame(['smtp://localhost:225', 'null://localhost:25'], $requestedDsns);
    }

    /**
     * @return array<string, TransportFactoryInterface>
     */
    private function getFactories(): array
    {
        return [
            'smtp+oauth' => new SmtpOauthTransportFactoryDecorator(new EsmtpTransportFactory(), static::createStub(SmtpOauthAuthenticator::class)),
            'smtp' => new EsmtpTransportFactory(),
            'null' => new NullTransportFactory(),
            'sendmail' => new SendmailTransportFactory(),
        ];
    }

    private function getTransportFactory(): Transport
    {
        return new Transport($this->getFactories());
    }
}
