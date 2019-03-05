<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;

class MailSender
{
    /**
     * @var \Swift_Mailer
     */
    private $swiftMailer;

    public function __construct(\Swift_Mailer $swiftMailer)
    {
        $this->swiftMailer = $swiftMailer;
    }

    /**
     * @throws MailTransportFailedException
     */
    public function send(\Swift_Message $message): void
    {
        $failedRecipients = [];

        $this->swiftMailer->send($message, $failedRecipients);

        if (!empty($failedRecipients)) {
            throw new MailTransportFailedException($failedRecipients);
        }
    }
}
