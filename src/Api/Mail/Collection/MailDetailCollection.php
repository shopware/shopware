<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Collection;

use Shopware\Api\Mail\Struct\MailDetailStruct;
use Shopware\Api\Order\Collection\OrderStateBasicCollection;

class MailDetailCollection extends MailBasicCollection
{
    /**
     * @var MailDetailStruct[]
     */
    protected $elements = [];

    public function getOrderStates(): OrderStateBasicCollection
    {
        return new OrderStateBasicCollection(
            $this->fmap(function (MailDetailStruct $mail) {
                return $mail->getOrderState();
            })
        );
    }

    public function getAttachmentUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getAttachments()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAttachments(): MailAttachmentBasicCollection
    {
        $collection = new MailAttachmentBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getAttachments()->getElements());
        }

        return $collection;
    }

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTranslations(): MailTranslationBasicCollection
    {
        $collection = new MailTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return MailDetailStruct::class;
    }
}
