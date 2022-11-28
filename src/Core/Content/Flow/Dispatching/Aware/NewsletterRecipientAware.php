<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
interface NewsletterRecipientAware extends FlowEventAware
{
    public const NEWSLETTER_RECIPIENT_ID = 'newsletterRecipientId';

    public const NEWSLETTER_RECIPIENT = 'newsletterRecipient';

    public function getNewsletterRecipientId(): string;
}
