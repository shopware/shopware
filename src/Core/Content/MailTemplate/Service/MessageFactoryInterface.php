<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

/**
 * @feature-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_12246) interface will be removed
 */
interface MessageFactoryInterface
{
    public function createMessage(string $subject, array $sender, array $recipients, array $contents, array $attachments, ?array $binAttachments = null): ?\Swift_Message;
}
