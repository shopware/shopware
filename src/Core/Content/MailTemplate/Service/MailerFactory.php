<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class MailerFactory
{
    public function create(SystemConfigService $configService, \Swift_Mailer $mailer): \Swift_Mailer
    {
        $emailAgent = $configService->get('core.mailerSettings.emailAgent');

        if ($emailAgent !== 'smtp') {
            return $mailer;
        }

        $transport = new \Swift_SmtpTransport(
            $configService->get('core.mailerSettings.host'),
            $configService->get('core.mailerSettings.port'),
            $this->getEncryption($configService)
        );

        $auth = $this->getAuthMode($configService);
        if ($auth) {
            $transport->setAuthMode($auth);
        }

        $transport->setUsername(
            (string) $configService->get('core.mailerSettings.username')
        );
        $transport->setPassword(
            (string) $configService->get('core.mailerSettings.password')
        );

        $mailer = new \Swift_Mailer($transport);

        return $mailer;
    }

    private function getEncryption(SystemConfigService $configService): ?string
    {
        $encryption = $configService->get('core.mailerSettings.encryption');

        switch ($encryption) {
            case 'ssl':
                return 'ssl';
            case 'tsl':
                return 'tsl';
            default:
                return null;
        }
    }

    private function getAuthMode(SystemConfigService $configService): ?string
    {
        $authenticationMethod = $configService->get('core.mailerSettings.authenticationMethod');

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
}
