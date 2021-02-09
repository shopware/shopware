<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

abstract class AbstractMailSender
{
    abstract public function getDecorated(): AbstractMailSender;

    /**
     * @throws MailTransportFailedException
     */
    abstract public function send(Email $email, ?Envelope $envelope = null): void;
}
