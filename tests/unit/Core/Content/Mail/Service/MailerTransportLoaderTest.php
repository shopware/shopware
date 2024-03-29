<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use Doctrine\DBAL\Exception\DriverException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Mail\Service\MailerTransportDecorator;
use Shopware\Core\Content\Mail\Service\MailerTransportLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\NullTransportFactory;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

/**
 * @internal
 */
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
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(EntityRepository::class)
        );

        $trans = $loader->fromString('smtp://localhost:25');

        static::assertInstanceOf(MailerTransportDecorator::class, $trans);

        $decorated = ReflectionHelper::getPropertyValue($trans, 'decorated');

        static::assertInstanceOf(EsmtpTransport::class, $decorated);
    }

    public function testFactoryWithLocal(): void
    {
        $factory = new MailerTransportLoader(
            $this->getTransportFactory(),
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => null,
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(EntityRepository::class)
        );

        $mailer = $factory->fromString('null://null');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        $decorated = ReflectionHelper::getPropertyValue($mailer, 'decorated');

        static::assertInstanceOf(SendmailTransport::class, $decorated);
    }

    #[DataProvider('providerSmtpEncryption')]
    public function testLoaderWithSmtpConfig(?string $encryption): void
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
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(FilesystemOperator::class),
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
    public static function providerSmtpEncryption(): iterable
    {
        yield 'tls' => ['tls'];
        yield 'ssl' => ['ssl'];
        yield 'null' => [null];
    }

    public function testFactoryWithLocalAndInvalidConfig(): void
    {
        $loader = new MailerTransportLoader(
            $this->getTransportFactory(),
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => '-t bla',
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(EntityRepository::class)
        );

        static::expectException(MailException::class);
        static::expectExceptionMessage('Given sendmail option "bla" is invalid');

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
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(EntityRepository::class)
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
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(EntityRepository::class)
        );

        static::expectException(MailException::class);
        static::expectExceptionMessage('Invalid mail agent given "test"');

        $loader->fromString('null://null');
    }

    public function testFactoryNoConnection(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willThrowException(DriverException::typeExists('no connection'));

        $loader = new MailerTransportLoader(
            $this->getTransportFactory(),
            $config,
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(EntityRepository::class)
        );

        $mailer = $loader->fromString('null://null');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        $decorated = ReflectionHelper::getPropertyValue($mailer, 'decorated');

        static::assertInstanceOf(NullTransport::class, $decorated);
    }

    public function testLoadMultipleMailers(): void
    {
        $loader = new MailerTransportLoader(
            $this->getTransportFactory(),
            new StaticSystemConfigService([
                'core.mailerSettings.emailAgent' => 'smtp',
                'core.mailerSettings.host' => 'localhost',
                'core.mailerSettings.port' => '225',
                'core.mailerSettings.username' => 'root',
                'core.mailerSettings.password' => 'root',
                'core.mailerSettings.encryption' => 'foo',
                'core.mailerSettings.authenticationMethod' => 'cram-md5',
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(EntityRepository::class)
        );

        $dsns = [
            'main' => 'null://localhost:25',
            'fallback' => 'null://localhost:25',
        ];

        $transports = ReflectionHelper::getPropertyValue($loader->fromStrings($dsns), 'transports');
        static::assertArrayHasKey('main', $transports);
        static::assertArrayHasKey('fallback', $transports);

        $mainMailer = $transports['main'];
        static::assertInstanceOf(MailerTransportDecorator::class, $mainMailer);

        $decorated = ReflectionHelper::getPropertyValue($mainMailer, 'decorated');
        static::assertInstanceOf(EsmtpTransport::class, $decorated);

        $fallbackMailer = $transports['fallback'];
        static::assertInstanceOf(MailerTransportDecorator::class, $fallbackMailer);

        $decorated = ReflectionHelper::getPropertyValue($fallbackMailer, 'decorated');
        static::assertInstanceOf(NullTransport::class, $decorated);
    }

    /**
     * @return array<string, AbstractTransportFactory>
     */
    private function getFactories(): array
    {
        return [
            'smtp' => new EsmtpTransportFactory(),
            'null' => new NullTransportFactory(),
        ];
    }

    private function getTransportFactory(): Transport
    {
        return new Transport($this->getFactories());
    }
}
