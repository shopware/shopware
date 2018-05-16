<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Snippet\SnippetDefinition;

class SnippetWrittenEvent extends WrittenEvent
{
    public const NAME = 'snippet.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return SnippetDefinition::class;
    }
}
