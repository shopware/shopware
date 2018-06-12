<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailAttachment;

use Shopware\Core\Framework\ORM\EntityCollection;


class MailAttachmentCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\System\Mail\Aggregate\MailAttachment\MailAttachmentStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MailAttachmentStruct
    {
        return parent::get($id);
    }

    public function current(): MailAttachmentStruct
    {
        return parent::current();
    }

    public function getMailIds(): array
    {
        return $this->fmap(function (MailAttachmentStruct $mailAttachment) {
            return $mailAttachment->getMailId();
        });
    }

    public function filterByMailId(string $id): self
    {
        return $this->filter(function (MailAttachmentStruct $mailAttachment) use ($id) {
            return $mailAttachment->getMailId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (MailAttachmentStruct $mailAttachment) {
            return $mailAttachment->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (MailAttachmentStruct $mailAttachment) use ($id) {
            return $mailAttachment->getMediaId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MailAttachmentStruct::class;
    }
}
