<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class MailSender implements MailSenderInterface
{
    /**
     * @var \Swift_Mailer
     */
    private $swiftMailer;

    /**
     * @var SystemConfigService
     */
    private $configService;

    public function __construct(\Swift_Mailer $swiftMailer, SystemConfigService $configService)
    {
        $this->swiftMailer = $swiftMailer;
        $this->configService = $configService;
    }

    /**
     * @throws MailTransportFailedException
     */
    public function send(\Swift_Message $message): void
    {
        $failedRecipients = [];

        $disabled = $this->configService->get('core.mailerSettings.disableDelivery');
        if ($disabled) {
            return;
        }

        $deliveryAddress = $this->configService->get('core.mailerSettings.deliveryAddress');
        if ($deliveryAddress) {
            $message->addBcc($deliveryAddress);
        }

        $this->swiftMailer->send($message, $failedRecipients);

        if (!empty($failedRecipients)) {
            throw new MailTransportFailedException($failedRecipients);
        }
    }
}
