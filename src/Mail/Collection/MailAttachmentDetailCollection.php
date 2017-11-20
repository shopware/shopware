<?php declare(strict_types=1);

namespace Shopware\Mail\Collection;

use Shopware\Mail\Struct\MailAttachmentDetailStruct;
use Shopware\Media\Collection\MediaBasicCollection;
use Shopware\Shop\Collection\ShopBasicCollection;

class MailAttachmentDetailCollection extends MailAttachmentBasicCollection
{
    /**
     * @var MailAttachmentDetailStruct[]
     */
    protected $elements = [];

    public function getMails(): MailBasicCollection
    {
        return new MailBasicCollection(
            $this->fmap(function (MailAttachmentDetailStruct $mailAttachment) {
                return $mailAttachment->getMail();
            })
        );
    }

    public function getMedia(): MediaBasicCollection
    {
        return new MediaBasicCollection(
            $this->fmap(function (MailAttachmentDetailStruct $mailAttachment) {
                return $mailAttachment->getMedia();
            })
        );
    }

    public function getShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (MailAttachmentDetailStruct $mailAttachment) {
                return $mailAttachment->getShop();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return MailAttachmentDetailStruct::class;
    }
}
