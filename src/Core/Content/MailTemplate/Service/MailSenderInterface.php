<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

interface MailSenderInterface
{
    public function send(\Swift_Message $message): void;
}
