<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Exception\FeatureActiveException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @feature-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_12246) MailSender will be removed, use MailSender instead
 */
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
        if (Feature::isActive('FEATURE_NEXT_12246')) {
            throw new FeatureActiveException('FEATURE_NEXT_12246');
        }

        $failedRecipients = [];

        $disabled = $this->configService->get('core.mailerSettings.disableDelivery');
        if ($disabled) {
            return;
        }

        $deliveryAddress = $this->configService->getString('core.mailerSettings.deliveryAddress');
        if ($deliveryAddress !== '') {
            $message->addBcc($deliveryAddress);
        }

        $this->swiftMailer->send($message, $failedRecipients);

        if (!empty($failedRecipients)) {
            throw new MailTransportFailedException($failedRecipients);
        }
    }
}
