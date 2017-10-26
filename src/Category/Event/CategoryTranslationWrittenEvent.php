<?php declare(strict_types=1);

namespace Shopware\Category\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class CategoryTranslationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'category_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'category_translation';
    }
}
