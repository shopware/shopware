<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Context\Struct\TranslationContext;

class AttributeConfigurationTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'attribute_configuration_translation.written';

    /**
     * @var string[]
     */
    protected $attributeConfigurationTranslationUuids;

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

    public function __construct(array $attributeConfigurationTranslationUuids, TranslationContext $context, array $errors = [])
    {
        $this->attributeConfigurationTranslationUuids = $attributeConfigurationTranslationUuids;
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    /**
     * @return string[]
     */
    public function getAttributeConfigurationTranslationUuids(): array
    {
        return $this->attributeConfigurationTranslationUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(?NestedEvent $event): void
    {
        if ($event === null) {
            return;
        }
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}
