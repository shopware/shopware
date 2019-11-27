<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface NewsletterSubscriptionServiceInterface
{
    public const MAIL_TYPE_OPT_IN = 'newsletterDoubleOptIn';

    public const MAIL_TYPE_REGISTER = 'newsletterRegister';

    public const STATUS_NOT_SET = 'notSet';

    public const STATUS_OPT_IN = 'optIn';

    public const STATUS_OPT_OUT = 'optOut';

    public const STATUS_DIRECT = 'direct';

    /**
     * @throws ConstraintViolationException
     */
    public function subscribe(DataBag $requestDataBag, SalesChannelContext $context): void;

    /**
     * @throws ConstraintViolationException
     */
    public function confirm(DataBag $requestDataBag, SalesChannelContext $context): void;

    /**
     * @throws ConstraintViolationException
     */
    public function unsubscribe(DataBag $requestDataBag, SalesChannelContext $context): void;

    /**
     * @throws ConstraintViolationException
     */
    public function update(DataBag $requestDataBag, SalesChannelContext $context): void;
}
