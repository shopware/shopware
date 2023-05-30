<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
final class FlowMailVariables
{
    public const URL = 'url';
    public const TEMPLATE_DATA = 'templateData';
    public const SUBJECT = 'subject';
    public const SHOP_NAME = 'shopName';
    public const REVIEW_FORM_DATA = 'reviewFormData';
    public const RESET_URL = 'resetUrl';
    public const RECIPIENTS = 'recipients';
    public const EVENT_NAME = 'name';
    public const MEDIA_ID = 'mediaId';
    public const EMAIL = 'email';
    public const CONTACT_FORM_DATA = 'contactFormData';
    public const CONTENTS = 'contents';
    public const CONTEXT_TOKEN = 'contextToken';
    public const CONFIRM_URL = 'confirmUrl';
    public const DATA = 'data';
}
