<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * The storage-side test terminal: it takes raw PHP property values and wraps them through
 * {@see StoredValue::fromDecoded()}, so a test never has to spell out the wrapping by hand.
 *
 * @final
 */
#[Package('framework')]
class StoredElementBuilder
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
     * @var array<string, list<StoredElement>>
     */
    private array $slots = [];

    private ElementStyle $style;

    /**
     * @var array<string, string>
     */
    private array $attributedSpecifications = [];

    private function __construct(
        private readonly string $component,
        ?string $id = null
    ) {
        $this->id = $id ?? Uuid::randomHex();
        $this->style = new ElementStyle();
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
     * @param list<StoredElement> $children
     */
    public function withSlot(string $name, array $children): self
    {
        $this->slots[$name] = $children;

        return $this;
    }

    public function withStyle(ElementStyle $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function withAttributedSpecification(string $referencePropertyKey, string $bindingSpecificationId): self
    {
        $this->attributedSpecifications[$referencePropertyKey] = $bindingSpecificationId;

        return $this;
    }

    public function build(): StoredElement
    {
        $properties = [];
        foreach ($this->properties as $key => $value) {
            $properties[$key] = StoredValue::fromDecoded($value);
        }

        return new StoredElement(
            id: $this->id,
            component: $this->component,
            dataRequirements: $this->dataRequirements,
            properties: $properties,
            slots: $this->slots,
            contextDefinitions: new ContextDefinitions($this->providers, $this->consumers),
            style: $this->style,
            attributedSpecifications: $this->attributedSpecifications
        );
    }
}
