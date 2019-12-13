<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

interface MessageFactoryInterface
{
    public function createMessage(string $subject, array $sender, array $recipients, array $contents, array $attachments, ?array $binAttachments = null): ?\Swift_Message;
}
