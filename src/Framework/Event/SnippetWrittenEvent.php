<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class SnippetWrittenEvent extends WrittenEvent
{
    const NAME = 'snippet.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'snippet';
    }
}
