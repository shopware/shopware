<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

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
