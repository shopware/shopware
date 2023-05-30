<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class NewsletterRecipientIndexingMessage extends EntityIndexingMessage
{
    private bool $deletedNewsletterRecipients = false;

    public function isDeletedNewsletterRecipients(): bool
    {
        return $this->deletedNewsletterRecipients;
    }

    public function setDeletedNewsletterRecipients(bool $deletedNewsletterRecipients): void
    {
        $this->deletedNewsletterRecipients = $deletedNewsletterRecipients;
    }
}
