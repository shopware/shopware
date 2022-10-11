<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @internal
 */
class MailerTransportLoader
{
    private Transport $envBasedTransport;

    private SystemConfigService $configService;

    /**
     * @internal
     */
    public function __construct(Transport $envBasedTransport, SystemConfigService $configService)
    {
        $this->envBasedTransport = $envBasedTransport;
        $this->configService = $configService;
    }

    public function fromString(string $dsn): TransportInterface
    {
        if (trim($this->configService->getString('core.mailerSettings.emailAgent')) === '') {
            return $this->envBasedTransport->fromString($dsn);
        }

        return $this->create();
    }

    private function create(): TransportInterface
    {
        $emailAgent = $this->configService->getString('core.mailerSettings.emailAgent');

        switch ($emailAgent) {
            case 'smtp':
                return $this->createSmtpTransport($this->configService);
            case 'local':
                return new SendmailTransport($this->getSendMailCommandLineArgument($this->configService));
            default:
                throw new \RuntimeException(sprintf('Invalid mail agent given "%s"', $emailAgent));
        }
    }

    private function createSmtpTransport(SystemConfigService $configService): TransportInterface
    {
        $dsn = new Dsn(
            $this->getEncryption($configService) === 'ssl' ? 'smtps' : 'smtp',
            $configService->getString('core.mailerSettings.host'),
            $configService->getString('core.mailerSettings.username'),
            $configService->getString('core.mailerSettings.password'),
            $configService->getInt('core.mailerSettings.port'),
            $this->getEncryption($configService) !== null ? [] : ['verify_peer' => 0]
        );

        return $this->envBasedTransport->fromDsnObject($dsn);
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

    private function getSendMailCommandLineArgument(SystemConfigService $configService): string
    {
        $command = '/usr/sbin/sendmail ';

        $option = $configService->getString('core.mailerSettings.sendMailOptions');

        if ($option === '') {
            $option = '-t';
        }

        if ($option !== '-bs' && $option !== '-t') {
            throw new \RuntimeException(sprintf('Given sendmail option "%s" is invalid', $option));
        }

        return $command . $option;
    }
}
