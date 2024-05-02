<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - Class will be removed
 */
#[Package('buyers-experience')]
class MailTemplateActions
{
    /**
     * @deprecated tag:v6.7.0 - Will be removed use `Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction::ACTION_NAME` instead
     */
    final public const MAIL_TEMPLATE_MAIL_SEND_ACTION = SendMailAction::ACTION_NAME;
}
