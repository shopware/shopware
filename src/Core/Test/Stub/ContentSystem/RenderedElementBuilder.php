<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * The rendering-side test terminal. Unlike {@see StoredElementBuilder} it wraps nothing: a rendered
 * property value is already the raw PHP value the model carries, hydrated entities included.
 *
 * @final
 */
#[Package('framework')]
class RenderedElementBuilder
{
    private string $id;

    /**
     * @var array<string, mixed>
     */
    private array $properties = [];

    /**
     * @var array<string, list<RenderedElement>>
     */
    private array $slots = [];

    private ElementStyle $style;

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

    /**
     * @param list<RenderedElement> $children
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

    public function build(): RenderedElement
    {
        return new RenderedElement(
            id: $this->id,
            component: $this->component,
            properties: $this->properties,
            slots: $this->slots,
            style: $this->style
        );
    }
}
