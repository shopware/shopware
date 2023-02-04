<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Doctrine\DBAL\Exception\DriverException;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Transports;

/**
 * @internal
 */
#[Package('system-settings')]
class MailerTransportLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Transport $envBasedTransport,
        private readonly SystemConfigService $configService,
        private readonly MailAttachmentsBuilder $attachmentsBuilder,
        private readonly FilesystemOperator $filesystem,
        private readonly EntityRepository $documentRepository
    ) {
    }

    /**
     * @param array<string, string> $dsns
     */
    public function fromStrings(array $dsns): Transports
    {
        $transports = [];
        foreach ($dsns as $name => $dsn) {
            if ($name === 'main') {
                $transports[$name] = $this->fromString($dsn);
            } else {
                $transports[$name] = $this->createTransportUsingDSN($dsn);
            }
        }

        return new Transports($transports);
    }

    public function fromString(string $dsn): TransportInterface
    {
        try {
            $transportConfig = trim($this->configService->getString('core.mailerSettings.emailAgent'));

            if ($transportConfig === '') {
                return $this->createTransportUsingDSN($dsn);
            }
        } catch (DriverException) {
            // We don't have a database connection right now
            return $this->createTransportUsingDSN($dsn);
        }

        return new MailerTransportDecorator(
            $this->create(),
            $this->attachmentsBuilder,
            $this->filesystem,
            $this->documentRepository
        );
    }

    public function createTransportUsingDSN(string $dsn): MailerTransportDecorator
    {
        return new MailerTransportDecorator(
            $this->envBasedTransport->fromString($dsn),
            $this->attachmentsBuilder,
            $this->filesystem,
            $this->documentRepository
        );
    }

    private function create(): TransportInterface
    {
        $emailAgent = $this->configService->getString('core.mailerSettings.emailAgent');

        return match ($emailAgent) {
            'smtp' => $this->createSmtpTransport($this->configService),
            'local' => new SendmailTransport($this->getSendMailCommandLineArgument($this->configService)),
            default => throw new \RuntimeException(sprintf('Invalid mail agent given "%s"', $emailAgent)),
        };
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

        return match ($encryption) {
            'ssl' => 'ssl',
            'tls' => 'tls',
            default => null,
        };
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
