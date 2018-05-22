<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Snippet\Collection\SnippetBasicCollection;

class SnippetBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'snippet.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var SnippetBasicCollection
     */
    protected $snippets;

    public function __construct(SnippetBasicCollection $snippets, ApplicationContext $context)
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

    public function getSnippets(): SnippetBasicCollection
    {
        return $this->snippets;
    }
}
