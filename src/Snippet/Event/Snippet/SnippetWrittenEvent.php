<?php declare(strict_types=1);

namespace Shopware\Snippet\Event\Snippet;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Snippet\Definition\SnippetDefinition;

class SnippetWrittenEvent extends WrittenEvent
{
    const NAME = 'snippet.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return SnippetDefinition::class;
    }
}
