<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Event\Snippet;

use Shopware\Application\Application\Event\Application\ApplicationBasicLoadedEvent;
use Shopware\System\Snippet\Collection\SnippetDetailCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class SnippetDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'snippet.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var SnippetDetailCollection
     */
    protected $snippets;

    public function __construct(SnippetDetailCollection $snippets, ApplicationContext $context)
    {
        $this->context = $context;
        $this->snippets = $snippets;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
        if ($this->snippets->getApplications()->count() > 0) {
            $events[] = new ApplicationBasicLoadedEvent($this->snippets->getApplications(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
