<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\_helper;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
final class ContentElementBuilder
{
    private string $id;

    /**
     * @var array<string, mixed>
     */
    private array $properties = [];

    /**
     * @var array<string, DataRequirement>
     */
    private array $dataRequirements = [];

    /**
     * @var array<string, ContextProvider>
     */
    private array $providers = [];

    /**
     * @var array<string, ContextConsumer>
     */
    private array $consumers = [];

    /**
     * @var array<string, list<ContentElement>>
     */
    private array $slots = [];

    private function __construct(
        private readonly string $component,
        ?string $id = null
    ) {
        $this->id = $id ?? Uuid::randomHex();
    }

    public static function create(string $component, ?string $id = null): self
    {
        return new self($component, $id);
    }

    public function withProperty(string $key, mixed $value): self
    {
        $this->properties[$key] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function withProperties(array $properties): self
    {
        $this->properties = array_merge($this->properties, $properties);

        return $this;
    }

    public function withDataRequirement(string $key, string $source, AbstractContentDataLoaderConfig $config): self
    {
        $this->dataRequirements[$key] = new DataRequirement($key, $source, $config);

        return $this;
    }

    public function withProvider(string $key, DistributionConfig $distribution, ContextType $type = ContextType::Single): self
    {
        $this->providers[$key] = new ContextProvider($type, $distribution);

        return $this;
    }

    public function withConsumer(
        string $key,
        ContextType $type,
        bool $required = false,
        bool $redistribute = false,
        ?string $consumerAlias = null,
        ?string $propertyAlias = null,
    ): self {
        $this->consumers[$key] = new ContextConsumer($type, $required, $redistribute, $consumerAlias, $propertyAlias);

        return $this;
    }

    /**
     * @param list<ContentElement> $children
     */
    public function withSlot(string $name, array $children): self
    {
        $this->slots[$name] = $children;

        return $this;
    }

    public function build(): ContentElement
    {
        $slots = [];
        foreach ($this->slots as $name => $children) {
            $slot = new SlotContent();
            foreach ($children as $child) {
                $slot->add($child);
            }
            $slots[$name] = $slot;
        }

        return new ContentElement(
            id: $this->id,
            component: $this->component,
            dataRequirements: $this->dataRequirements,
            properties: $this->properties,
            slots: $slots,
            contextDefinitions: new ContextDefinitions($this->providers, $this->consumers)
        );
    }
}
