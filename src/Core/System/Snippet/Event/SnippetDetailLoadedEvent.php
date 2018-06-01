<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Event;

use Shopware\System\Touchpoint\Event\TouchpointBasicLoadedEvent;
use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Snippet\Collection\SnippetDetailCollection;

class SnippetDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'snippet.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var SnippetDetailCollection
     */
    protected $snippets;

    public function __construct(SnippetDetailCollection $snippets, Context $context)
    {
        $this->context = $context;
        $this->snippets = $snippets;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSnippets(): SnippetDetailCollection
    {
        return $this->snippets;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->snippets->getTouchpoints()->count() > 0) {
            $events[] = new TouchpointBasicLoadedEvent($this->snippets->getTouchpoints(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
