<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Snippet\SnippetDefinition;

class SnippetDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'snippet.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return SnippetDefinition::class;
    }
}
