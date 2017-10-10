<?php declare(strict_types=1);

namespace Shopware\Search\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Symfony\Component\DependencyInjection\Container;

class SearchIndexWrittenEvent extends NestedEvent
{
    const NAME = 's_search_index.written';

    /**
     * @var NestedEventCollection
     */
    protected $events;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var string[]
     */
    protected $keywordIDs = [];
    /**
     * @var string[]
     */
    protected $fieldIDs = [];
    /**
     * @var string[]
     */
    protected $elementIDs = [];

    /**
     * @var array
     */
    private $rawData;

    public function __construct(array $primaryKeys, TranslationContext $context, array $rawData = [], array $errors = [])
    {
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
        $this->rawData = $rawData;

        foreach ($primaryKeys as $key => $value) {
            if ($key === 'uuid') {
                $key = 'SearchIndexUuid';
            }

            $key = lcfirst(Container::camelize($key)) . 's';
            $this->$key = $value;
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function getKeywordIDs(): array
    {
        return $this->keywordIDs;
    }

    public function getFieldIDs(): array
    {
        return $this->fieldIDs;
    }

    public function getElementIDs(): array
    {
        return $this->elementIDs;
    }
}
