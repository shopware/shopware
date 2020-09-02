<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class MailerTransportFactory implements MailerTransportFactoryInterface
{
    public function create(SystemConfigService $configService, \Swift_Transport $innerTransport): \Swift_Transport
    {
        $emailAgent = $configService->getString('core.mailerSettings.emailAgent');

        if ($emailAgent === '') {
            return $innerTransport;
        }

        switch ($emailAgent) {
            case 'smtp':
                return $this->createSmtpTransport($configService);
            case 'local':
                return new \Swift_SendmailTransport($this->getSendMailCommandLineArgument($configService));
            default:
                throw new \RuntimeException(sprintf('Invalid mail agent given "%s"', $emailAgent));
        }
    }

    protected function createSmtpTransport(SystemConfigService $configService): \Swift_Transport
    {
        $transport = new \Swift_SmtpTransport(
            $configService->getString('core.mailerSettings.host'),
            $configService->getInt('core.mailerSettings.port'),
            $this->getEncryption($configService)
        );

        $auth = $this->getAuthMode($configService);
        if ($auth) {
            $transport->setAuthMode($auth);
        }

        $transport->setUsername($configService->getString('core.mailerSettings.username'));
        $transport->setPassword($configService->getString('core.mailerSettings.password'));

        return $transport;
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

    private function getAuthMode(SystemConfigService $configService): ?string
    {
        $authenticationMethod = $configService->getString('core.mailerSettings.authenticationMethod');

        switch ($authenticationMethod) {
            case 'plain':
                return 'plain';
            case 'login':
                return 'login';
            case 'cram-md5':
                return 'cram-md5';
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
