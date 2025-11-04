<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\PlaceholderCollectorVisitor;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Content element aggregate root with tree traversal.
 *
 * @internal
 */
#[Package('discovery')]
class ContentElement extends Struct
{
    /**
     * @param array<string, DataRequirement> $dataRequirements Indexed by key
     * @param array<string, mixed> $properties
     * @param array<string, SlotContent> $slots Named slots containing child elements
     */
    public function __construct(
        protected string $id,
        protected string $type,
        protected array $dataRequirements = [],
        protected array $properties = [],
        protected array $slots = [],
        protected ContextDefinitions $contextDefinitions = new ContextDefinitions([], [])
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function requiresData(): bool
    {
        return !empty($this->dataRequirements);
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
        return $this->properties;
    }

    public function getProperty(string $key): mixed
    {
        return $this->properties[$key] ?? null;
    }

    public function hasProperty(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    public function setProperty(string $key, mixed $value): void
    {
        $this->properties[$key] = $value;
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
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
            foreach ($slotContent as $element) {
                yield $element;
            }
        }
    }

    public function hasSlots(): bool
    {
        return !empty($this->slots);
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

    public function acceptsContext(string $key): bool
    {
        $acceptedKeys = array_keys($this->contextDefinitions->getAllConsumers());

        foreach ($acceptedKeys as $acceptedKey) {
            if (ContextPathResolver::matches($key, $acceptedKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<ContentElement>
     */
    public function collectConsumers(string $contextKey): array
    {
        $consumers = [];

        foreach ($this->allSlotElements() as $child) {
            if ($child->acceptsContext($contextKey)) {
                $consumers[] = $child;
            }
        }

        return $consumers;
    }

    public function replacePlaceholders(RenderingSpecification $specification): void
    {
        foreach ($this->properties as $key => $value) {
            if (\is_string($value)) {
                $this->properties[$key] = $this->resolvePlaceholder($value, $specification->placeholderValues);
            }
        }

        foreach ($this->allSlotElements() as $child) {
            $child->replacePlaceholders($specification);
        }
    }

    /**
     * @return array<string>
     */
    public function getPlaceholders(): array
    {
        $visitor = new PlaceholderCollectorVisitor();
        $this->traverse($visitor);

        return $visitor->getPlaceholders();
    }

    public function getApiAlias(): string
    {
        return 'content_element';
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
