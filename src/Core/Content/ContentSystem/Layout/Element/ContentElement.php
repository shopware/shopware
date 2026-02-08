<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Content element aggregate root with tree traversal.
 *
 * @final
 */
#[Package('discovery')]
class ContentElement extends Struct
{
    /**
     * @var array<string, Struct>
     */
    protected array $structProperties = [];

    /**
     * @var array<string, mixed>
     */
    protected array $nonStructProperties = [];

    /**
     * @param array<string, DataRequirement> $dataRequirements
     * @param array<string, mixed> $properties
     * @param array<string, SlotContent> $slots
     */
    public function __construct(
        protected string $id,
        protected string $component,
        protected array $dataRequirements = [],
        array $properties = [],
        protected array $slots = [],
        protected ContextDefinitions $contextDefinitions = new ContextDefinitions([], [])
    ) {
        $this->setProperties($properties);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function requiresData(): bool
    {
        return \count($this->dataRequirements) !== 0;
    }

    /**
     * @return array<string, DataRequirement>
     */
    public function getDataRequirements(): array
    {
        return $this->dataRequirements;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return array_merge($this->structProperties, $this->nonStructProperties);
    }

    /**
     * Returns null when the property doesn't exist
     */
    public function getProperty(string $key): mixed
    {
        if (\array_key_exists($key, $this->structProperties)) {
            return $this->structProperties[$key];
        }

        if (\array_key_exists($key, $this->nonStructProperties)) {
            return $this->nonStructProperties[$key];
        }

        return null;
    }

    public function hasProperty(string $key): bool
    {
        return \array_key_exists($key, $this->structProperties) || \array_key_exists($key, $this->nonStructProperties);
    }

    public function setProperty(string $key, mixed $value): void
    {
        if ($value instanceof Struct) {
            $this->structProperties[$key] = $value;

            return;
        }

        $this->nonStructProperties[$key] = $value;
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function setProperties(array $properties): void
    {
        $this->structProperties = [];
        $this->nonStructProperties = [];

        foreach ($properties as $key => $value) {
            $this->setProperty($key, $value);
        }
    }

    /**
     * @return array<string, SlotContent>
     */
    public function getSlots(): array
    {
        return $this->slots;
    }

    /**
     * Yields all direct child elements from all slots (one level only).
     * For recursive tree traversal, use traverse() with an ElementVisitor.
     *
     * @return \Generator<ContentElement>
     */
    public function allSlotElements(): \Generator
    {
        foreach ($this->slots as $slotContent) {
            yield from $slotContent;
        }
    }

    public function hasSlots(): bool
    {
        return \count($this->slots) !== 0;
    }

    public function traverse(ElementVisitor $visitor): void
    {
        $visitor->enter($this);

        foreach ($this->allSlotElements() as $child) {
            $child->traverse($visitor);
        }

        $visitor->leave($this);
    }

    /**
     * @return array<string, ContextProvider>
     */
    public function getProvidesContext(): array
    {
        return $this->contextDefinitions->getAllProviders();
    }

    /**
     * @return array<string, ContextConsumer>
     */
    public function getAcceptsContext(): array
    {
        return $this->contextDefinitions->getAllConsumers();
    }

    public function getContextDefinitions(): ContextDefinitions
    {
        return $this->contextDefinitions;
    }

    public function setContextDefinitions(ContextDefinitions $contextDefinitions): void
    {
        $this->contextDefinitions = $contextDefinitions;
    }

    public function acceptsContext(string $key, ContextPathResolver $pathResolver): bool
    {
        $acceptedKeys = array_keys($this->contextDefinitions->getAllConsumers());

        foreach ($acceptedKeys as $acceptedKey) {
            if ($pathResolver->matches($key, $acceptedKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<ContentElement>
     */
    public function collectConsumers(string $contextKey, ContextPathResolver $pathResolver): array
    {
        $consumers = [];

        foreach ($this->allSlotElements() as $child) {
            if ($child->acceptsContext($contextKey, $pathResolver)) {
                $consumers[] = $child;
            }
        }

        return $consumers;
    }

    public function replacePlaceholders(RenderingSpecification $specification): void
    {
        foreach ($this->nonStructProperties as $key => $value) {
            if (\is_string($value)) {
                $this->nonStructProperties[$key] = $this->resolvePlaceholder($value, $specification->placeholderValues);
            }
        }

        foreach ($this->allSlotElements() as $child) {
            $child->replacePlaceholders($specification);
        }
    }

    public function getApiAlias(): string
    {
        return 'content_element';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        // Remove internal structCache from output (should not be exposed via API)
        unset($data['structProperties']);
        unset($data['nonStructProperties']);

        $data['properties'] = array_merge(
            $this->structProperties,
            $this->nonStructProperties
        );

        return $data;
    }

    private function resolvePlaceholder(string $input, PlaceholderValues $values): string
    {
        foreach ($values->all() as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $input = \str_replace($placeholder, (string) $value, $input);
        }

        return $input;
    }
}
