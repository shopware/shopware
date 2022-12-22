<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Mail\Service\MailerTransportDecorator;
use Shopware\Core\Content\Mail\Service\MailerTransportFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Test\Annotation\DisabledFeatures;
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
 *
 * @DisabledFeatures(features={"v6.5.0.0"})
 */
class MailerTransportFactoryTest extends TestCase
{
    public function testUseSymfonyDefaultDSN(): void
    {
        $factory = new MailerTransportFactory(
            $this->getFactories(),
            new ConfigService([
                'core.mailerSettings.emailAgent' => '',
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
        );

        $transport = $factory->fromString('smtp://localhost:25');

        static::assertInstanceOf(MailerTransportDecorator::class, $transport);

        $decorated = ReflectionHelper::getPropertyValue($transport, 'decorated');

        static::assertInstanceOf(EsmtpTransport::class, $decorated);

        $transport = $factory->fromString('sendmail://default');

        static::assertInstanceOf(MailerTransportDecorator::class, $transport);

        $decorated = ReflectionHelper::getPropertyValue($transport, 'decorated');

        static::assertInstanceOf(SendmailTransport::class, $decorated);

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
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
        );

        $mailer = $factory->fromString('smtp://example.com:1025');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        $decorated = ReflectionHelper::getPropertyValue($mailer, 'decorated');

        static::assertEquals(\get_class($original), \get_class($decorated));
    }

    public function testFactoryWithLocal(): void
    {
        $original = new SendmailTransport();

        $factory = new MailerTransportFactory(
            $this->getFactories(),
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

        static::assertEquals(\get_class($original), \get_class($decorated));
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
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
        );

        $mailer = $factory->fromString('null://null');

        static::assertInstanceOf(MailerTransportDecorator::class, $mailer);

        $decorated = ReflectionHelper::getPropertyValue($mailer, 'decorated');

        static::assertEquals(\get_class($original), \get_class($decorated));
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
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
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
            ]),
            $this->createMock(MailAttachmentsBuilder::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EntityRepository::class)
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
