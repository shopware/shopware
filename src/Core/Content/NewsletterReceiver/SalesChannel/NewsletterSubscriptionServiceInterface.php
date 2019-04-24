<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver\SalesChannel;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

interface NewsletterSubscriptionServiceInterface
{
    public const MAIL_TYPE_OPT_IN = 'newsletterDoubleOptIn';

    public const MAIL_TYPE_REGISTER = 'newsletterRegister';

    public const STATUS_NOT_SET = 'notSet';

    public const STATUS_OPT_IN = 'optIn';

    public const STATUS_OPT_OUT = 'optOut';

    public const STATUS_DIRECT = 'direct';

    public function subscribe(DataBag $requestDataBag, Context $context): void;

    public function confirm(DataBag $requestDataBag, Context $context): void;

    public function unsubscribe(DataBag $requestDataBag, Context $context): void;
}
