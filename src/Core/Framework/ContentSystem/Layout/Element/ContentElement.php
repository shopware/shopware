<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Content element aggregate root with tree traversal.
 *
 * Lifecycle: ContentElement is a mutable object whose properties map changes between stages:
 *
 * - **Storage** (database JSON): properties contains only static/config values (scalars).
 *   FQCN-typed values are absent — their loading instructions live in $dataRequirements
 *   and $contextDefinitions.
 * - **Post-hydration** (runtime): properties contains static values AND loaded data merged.
 *   Data is written via setProperty($key, $data) using the data requirement's key.
 *   Context resolution writes via the same mechanism.
 * - **API output** (jsonSerialize): properties is serialized as a single merged map.
 *   Skeleton output strips properties entirely.
 *
 * @final
 */
#[Package('framework')]
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

    /**
     * @codeCoverageIgnore
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getComponent(): string
    {
        return $this->component;
    }

    public function requiresData(): bool
    {
        return \count($this->dataRequirements) !== 0;
    }

    /**
     * @codeCoverageIgnore
     *
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

    /**
     * Sets a property value. Called at different lifecycle stages:
     * - Design time: static config values (persisted)
     * - Hydration: loaded data stored under the data requirement key
     * - Context resolution: context data stored under the property alias or consumer key
     *
     * After hydration, there is no distinction between these sources.
     */
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
     * @codeCoverageIgnore
     *
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

    /**
     * @codeCoverageIgnore
     */
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
     * @codeCoverageIgnore
     *
     * @return array<string, ContextProvider>
     */
    public function getProvidesContext(): array
    {
        return $this->contextDefinitions->getAllProviders();
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, ContextConsumer>
     */
    public function getAcceptsContext(): array
    {
        return $this->contextDefinitions->getAllConsumers();
    }

    /**
     * @codeCoverageIgnore
     */
    public function getContextDefinitions(): ContextDefinitions
    {
        return $this->contextDefinitions;
    }

    /**
     * @codeCoverageIgnore
     */
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

    /**
     * @codeCoverageIgnore
     */
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
        unset(
            $data['structProperties'],
            $data['nonStructProperties'],
        );

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
