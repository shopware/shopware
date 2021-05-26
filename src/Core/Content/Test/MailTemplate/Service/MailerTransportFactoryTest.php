<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\MailTemplate\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class MailerTransportFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testFactoryWithoutConfig(): void
    {
        $original = new EsmtpTransport();

        $factory = $this->getContainer()->get('mailer.transport_factory');

        $before = $_SERVER['MAILER_URL'] ?? 'null://localhost';

        $_SERVER['MAILER_URL'] = 'smtp://example.com:1025';

        $mailer = $factory->create(
            new ConfigService([
                'core.mailerSettings.emailAgent' => null,
            ])
        );

        $_SERVER['MAILER_URL'] = $before;

        static::assertEquals(\get_class($original), \get_class($mailer));
    }

    public function testFactoryWithLocal(): void
    {
        $original = new SendmailTransport();

        $factory = $this->getContainer()->get('mailer.transport_factory');

        $mailer = $factory->create(
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'local',
                'core.mailerSettings.sendMailOptions' => null,
            ])
        );

        static::assertEquals(\get_class($original), \get_class($mailer));
    }

    public function testFactoryWithConfig(): void
    {
        $original = new EsmtpTransport();

        $factory = $this->getContainer()->get('mailer.transport_factory');

        /** @var EsmtpTransport $mailer */
        $mailer = $factory->create(
            new ConfigService([
                'core.mailerSettings.emailAgent' => 'smtp',
                'core.mailerSettings.host' => 'localhost',
                'core.mailerSettings.port' => '225',
                'core.mailerSettings.username' => 'root',
                'core.mailerSettings.password' => 'root',
                'core.mailerSettings.encryption' => 'ssl',
                'core.mailerSettings.authenticationMethod' => 'cram-md5',
            ])
        );

        static::assertEquals(\get_class($original), \get_class($mailer));
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
