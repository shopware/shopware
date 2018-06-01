<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailAttachment\Collection;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Struct\MailAttachmentBasicStruct;

class MailAttachmentBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\System\Mail\Aggregate\MailAttachment\Struct\MailAttachmentBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MailAttachmentBasicStruct
    {
        return parent::get($id);
    }

    public function current(): MailAttachmentBasicStruct
    {
        return parent::current();
    }

    public function getMailIds(): array
    {
        return $this->fmap(function (MailAttachmentBasicStruct $mailAttachment) {
            return $mailAttachment->getMailId();
        });
    }

    public function filterByMailId(string $id): self
    {
        return $this->filter(function (MailAttachmentBasicStruct $mailAttachment) use ($id) {
            return $mailAttachment->getMailId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (MailAttachmentBasicStruct $mailAttachment) {
            return $mailAttachment->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (MailAttachmentBasicStruct $mailAttachment) use ($id) {
            return $mailAttachment->getMediaId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MailAttachmentBasicStruct::class;
    }
}
