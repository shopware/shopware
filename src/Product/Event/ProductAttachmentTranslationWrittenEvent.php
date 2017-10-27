<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductAttachmentTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'product_attachment_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_attachment_translation';
    }
}
