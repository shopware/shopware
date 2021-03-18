<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

class MailSender extends AbstractMailSender
{
    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var SystemConfigService
     */
    private $configService;

    public function __construct(Mailer $mailer, SystemConfigService $configService)
    {
        $this->mailer = $mailer;
        $this->configService = $configService;
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
