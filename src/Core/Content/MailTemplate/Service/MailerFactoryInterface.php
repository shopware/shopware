<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

interface MailerFactoryInterface
{
    public function create(\Swift_Message $message): \Swift_Mailer;
}
