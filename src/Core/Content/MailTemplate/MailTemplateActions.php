<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class MailTemplateActions
{
    final public const MAIL_TEMPLATE_MAIL_SEND_ACTION = 'action.mail.send';
}
