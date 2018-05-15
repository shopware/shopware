<?php declare(strict_types=1);

namespace Shopware\System\Mail\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Mail\Struct\MailAttachmentBasicStruct;

class MailAttachmentBasicCollection extends EntityCollection
{
    /**
     * @var MailAttachmentBasicStruct[]
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

    public function getShopIds(): array
    {
        return $this->fmap(function (MailAttachmentBasicStruct $mailAttachment) {
            return $mailAttachment->getShopId();
        });
    }

    public function filterByShopId(string $id): self
    {
        return $this->filter(function (MailAttachmentBasicStruct $mailAttachment) use ($id) {
            return $mailAttachment->getShopId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MailAttachmentBasicStruct::class;
    }
}
