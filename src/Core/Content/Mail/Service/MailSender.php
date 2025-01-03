<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\Content\Mail\Message\SendMailMessage;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

#[Package('services-settings')]
class MailSender extends AbstractMailSender
{
    public const DISABLE_MAIL_DELIVERY = 'core.mailerSettings.disableDelivery';

    private const BASE_FILE_SYSTEM_PATH = 'mail-data/';

    /**
     * @internal
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly FilesystemOperator $filesystem,
        private readonly SystemConfigService $configService,
        private readonly int $maxContentLength,
        private readonly ?MessageBusInterface $messageBus = null,
    ) {
    }

    public function getDecorated(): AbstractMailSender
    {
        throw new DecorationPatternException(self::class);
    }

    public function send(Email $email, ?Envelope $envelope = null): void
    {
        if ($envelope) {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The parameter $envelope is deprecated and will be removed.');
        }

        $disabled = $this->configService->get(self::DISABLE_MAIL_DELIVERY);

        if ($disabled) {
            return;
        }

        $deliveryAddress = $this->configService->getString('core.mailerSettings.deliveryAddress');
        if ($deliveryAddress !== '') {
            $email->addBcc($deliveryAddress);
        }

        if ($this->maxContentLength > 0 && \strlen($email->getBody()->toString()) > $this->maxContentLength) {
            throw MailException::mailBodyTooLong($this->maxContentLength);
        }

        if ($this->messageBus === null) {
            try {
                $this->transport->send($email);
            } catch (\Throwable $e) {
                throw MailException::mailTransportFailedException($e);
            }

            return;
        }

        $mailData = serialize($email);
        $mailDataPath = self::BASE_FILE_SYSTEM_PATH . Hasher::hash($mailData);

        $this->filesystem->write($mailDataPath, $mailData);
        $this->messageBus->dispatch(new SendMailMessage($mailDataPath));
    }
}
