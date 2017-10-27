<?php declare(strict_types=1);

namespace Shopware\Api\Write;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class GenericWrittenEvent extends NestedEvent
{
    const NAME = 'generic.entity.written';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var NestedEvent
     */
    protected $event;

    public function __construct(NestedEvent $event, TranslationContext $context)
    {
        $this->context = $context;
        $this->event = $event;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([$this->event]);
    }
}
