<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MailerTransportFactory extends Transport
{
    private SystemConfigService $configService;

    public function __construct(iterable $factories, SystemConfigService $configService)
    {
        parent::__construct($factories);
        $this->configService = $configService;
    }

    public function fromString(string $dsn): TransportInterface
    {
        return $this->create();
    }

    public function create(?SystemConfigService $configService = null): TransportInterface
    {
        if ($configService === null) {
            $configService = $this->configService;
        }

        $emailAgent = $configService->getString('core.mailerSettings.emailAgent');

        if ($emailAgent === '') {
            $mailerUrl = (string) EnvironmentHelper::getVariable('MAILER_URL', '');
            if ($mailerUrl !== '') {
                try {
                    return parent::fromString($mailerUrl);
                } catch (\Throwable $e) {
                    // Mailer Url not valid. Use standard sendmail
                }
            }
            $dsn = new Dsn(
                'sendmail',
                'default'
            );

            return $this->fromDsnObject($dsn);
        }

        switch ($emailAgent) {
            case 'smtp':
                return $this->createSmtpTransport($configService);
            case 'local':
                return new SendmailTransport('/usr/sbin/sendmail ' . ($configService->getString('core.mailerSettings.sendMailOptions') ?: '-bs'));
            default:
                throw new \RuntimeException(sprintf('Invalid mail agent given "%s"', $emailAgent));
        }
    }

    protected function createSmtpTransport(SystemConfigService $configService): TransportInterface
    {
        $dsn = new Dsn(
            $this->getEncryption($configService) === 'ssl' ? 'smtps' : 'smtp',
            $configService->getString('core.mailerSettings.host'),
            $configService->getString('core.mailerSettings.username'),
            $configService->getString('core.mailerSettings.password'),
            $configService->getInt('core.mailerSettings.port'),
            $this->getEncryption($configService) !== null ? [] : ['verify_peer' => 0]
        );

        return $this->fromDsnObject($dsn);
    }

    private function getEncryption(SystemConfigService $configService): ?string
    {
        $encryption = $configService->getString('core.mailerSettings.encryption');

        switch ($encryption) {
            case 'ssl':
                return 'ssl';
            case 'tls':
                return 'tls';
            default:
                return null;
        }
    }
}
