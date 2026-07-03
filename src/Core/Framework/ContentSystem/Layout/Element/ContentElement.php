<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Content element aggregate root with tree traversal. Mutable: its properties map is filled
 * across the storage → post-hydration → output stages.
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
     * @param array<string, string> $attributedSpecifications
     */
    public function __construct(
        protected string $id,
        protected string $component,
        protected array $dataRequirements = [],
        array $properties = [],
        protected array $slots = [],
        protected ContextDefinitions $contextDefinitions = new ContextDefinitions([], []),
        protected ElementStyle $style = new ElementStyle(),
        protected array $attributedSpecifications = []
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

    /**
     * @codeCoverageIgnore
     */
    public function getStyle(): ElementStyle
    {
        return $this->style;
    }

    public function requiresData(): bool
    {
        return $this->dataRequirements !== [];
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
     * @codeCoverageIgnore
     *
     * @return array<string, string>
     */
    public function getAttributedSpecifications(): array
    {
        return $this->attributedSpecifications;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return array_merge($this->structProperties, $this->nonStructProperties);
    }

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
     * Called at different lifecycle stages:
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

    public function hasSlots(): bool
    {
        return $this->slots !== [];
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
     * Canonical camelCase wire shape: id/component/properties always present, the rest omitted when
     * empty; extensions, apiAlias and the internal struct/non-struct property stores are never emitted.
     *
     * attributedSpecifications (admin/editor bookkeeping) is deliberately never emitted here: this is
     * the shape the Store API serializes directly, and attribution must not leak into it.
     * ContentElementFieldSerializer::serializeContentElement() re-serializes on top of this output to
     * add attribution back for storage and admin responses, recursing into slot children so nested
     * bound elements keep theirs too.
     *
     * An empty `properties` map is emitted as `[]`: PHP cannot carry an empty map as `{}` through a
     * shared serializer without breaking the array-typed write path, so `[]` is the single canonical shape.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'component' => $this->component,
            'properties' => $this->getProperties(),
        ];

        if ($this->dataRequirements !== []) {
            $data['dataRequirements'] = array_map(
                static fn (DataRequirement $requirement): array => $requirement->jsonSerialize(),
                $this->dataRequirements
            );
        }

        if ($this->slots !== []) {
            $slots = [];
            foreach ($this->slots as $slotName => $slotContent) {
                $children = [];
                foreach ($slotContent as $child) {
                    $children[] = $child->jsonSerialize();
                }
                $slots[$slotName] = $children;
            }
            $data['slots'] = $slots;
        }

        $providers = $this->contextDefinitions->getAllProviders();
        if ($providers !== []) {
            $data['providesContext'] = array_map(
                static fn (ContextProvider $provider): array => $provider->jsonSerialize(),
                $providers
            );
        }

        $consumers = $this->contextDefinitions->getAllConsumers();
        if ($consumers !== []) {
            $data['acceptsContext'] = array_map(
                static fn (ContextConsumer $consumer): array => $consumer->jsonSerialize(),
                $consumers
            );
        }

        if (!$this->style->isEmpty()) {
            $data['style'] = $this->style->toArray();
        }

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
