<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element;

use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\ElementSlots;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\PlaceholderCollectorVisitor;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\Struct\ResolvedData;
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
     */
    public function __construct(
        protected string $id,
        protected string $type,
        protected array $dataRequirements = [],
        protected array $properties = [],
        protected ElementSlots $slots = new ElementSlots([]),
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

    public function getSlots(): ElementSlots
    {
        return $this->slots;
    }

    public function traverse(ElementVisitor $visitor): void
    {
        $visitor->enter($this);

        foreach ($this->slots->allElements() as $child) {
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
        return $this->contextDefinitions->accepts($key);
    }

    /**
     * @return array<ContentElement>
     */
    public function collectConsumers(string $contextKey): array
    {
        $consumers = [];

        foreach ($this->slots->allElements() as $child) {
            if ($child->acceptsContext($contextKey)) {
                $consumers[] = $child;
            }
        }

        return $consumers;
    }

    public function replacePlaceholders(ResolvedData $data): void
    {
        foreach ($this->properties as $key => $value) {
            if (\is_string($value)) {
                $this->properties[$key] = $this->resolvePlaceholder($value, $data);
            }
        }

        foreach ($this->slots->allElements() as $child) {
            $child->replacePlaceholders($data);
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

    private function resolvePlaceholder(string $input, ResolvedData $data): string
    {
        $values = $data->getValues();

        foreach ($values as $key => $value) {
            if (\is_scalar($value)) {
                $placeholder = '{{' . $key . '}}';
                $input = \str_replace($placeholder, (string) $value, $input);
            }
        }

        return $input;
    }
}
