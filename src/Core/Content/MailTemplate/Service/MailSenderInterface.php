<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Framework\Feature;

/**
 * @feature-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_12246) MailSenderInterface will be removed, use AbstractMailSender instead
 */
interface MailSenderInterface
{
    public function send(\Swift_Message $message): void;
}
