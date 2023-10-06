<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Package('system-settings')]
class MailSender extends AbstractMailSender
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SystemConfigService $configService
    ) {
    }

    public function getDecorated(): AbstractMailSender
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @throws MailTransportFailedException
     */
    public function send(Email $email, ?Envelope $envelope = null): void
    {
        $failedRecipients = [];

        $disabled = $this->configService->get('core.mailerSettings.disableDelivery');
        if ($disabled) {
            return;
        }

        $deliveryAddress = $this->configService->getString('core.mailerSettings.deliveryAddress');
        if ($deliveryAddress !== '') {
            $email->addBcc($deliveryAddress);
        }

        try {
            $this->mailer->send($email, $envelope);
        } catch (\Throwable $e) {
            throw new MailTransportFailedException($failedRecipients, $e);
        }
    }
}
