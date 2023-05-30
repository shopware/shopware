<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - extends of FlowEventAware will be removed, implement the interface inside your event
 */
#[Package('business-ops')]
interface NewsletterRecipientAware extends FlowEventAware
{
    public const NEWSLETTER_RECIPIENT_ID = 'newsletterRecipientId';

    public const NEWSLETTER_RECIPIENT = 'newsletterRecipient';

    public function getNewsletterRecipientId(): string;
}
