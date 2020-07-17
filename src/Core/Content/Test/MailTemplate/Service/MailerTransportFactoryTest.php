<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\MailTemplate\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\MailerTransportFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class MailerTransportFactoryTest extends TestCase
{
    public function testFactoryWithoutConfig(): void
    {
        $original = new \Swift_NullTransport();
        $factory = new MailerTransportFactory();

        $mailer = $factory->create(
            new ConfigService([
                'core.mailerSettings.emailAgent' => null,
            ]),
            $original
        );

        static::assertSame($original, $mailer);
    }

    public function testFactoryWithLocal(): void
    {
        $original = new \Swift_NullTransport();

        $factory = new MailerTransportFactory();

        $mailer = $factory->create(
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => null,
            ]),
            $original
        );

        static::assertInstanceOf(\Swift_SendmailTransport::class, $mailer);
    }

    public function testFactoryWithConfig(): void
    {
        $original = new \Swift_NullTransport();
        $factory = new MailerTransportFactory();

        $transport = $factory->create(
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'smtp',
                'core.mailerSettings.host' => 'localhost',
                'core.mailerSettings.port' => '225',
                'core.mailerSettings.username' => 'root',
                'core.mailerSettings.password' => 'root',
                'core.mailerSettings.encryption' => 'ssl',
                'core.mailerSettings.authenticationMethod' => 'cram-md5',
            ]),
            $original
        );

        static::assertNotSame($original, $transport);

        /** @var \Swift_SmtpTransport $transport */
        static::assertSame('localhost', $transport->getHost());
        static::assertSame(225, $transport->getPort());
        static::assertSame('root', $transport->getUsername());
        static::assertSame('root', $transport->getPassword());
        static::assertSame('ssl', $transport->getEncryption());
        static::assertSame('cram-md5', $transport->getAuthMode());
    }
}

class ConfigService extends SystemConfigService
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function get(string $key, ?string $salesChannelId = null)
    {
        return $this->config[$key];
    }
}
