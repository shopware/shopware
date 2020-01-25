<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Framework\Context;

interface MailServiceInterface
{
    public function send(array $data, Context $context, array $templateData = []): ?\Swift_Message;
}
