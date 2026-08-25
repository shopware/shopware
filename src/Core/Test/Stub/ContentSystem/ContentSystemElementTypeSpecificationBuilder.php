<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class ContentSystemElementTypeSpecificationBuilder
{
    /**
     * @var array<string, PropertySpecification>
     */
    private array $properties = [];

    private function __construct(
        private readonly string $name,
        private readonly string $label,
    ) {
    }

    public static function create(string $name = 'Sw:Block', ?string $label = null): self
    {
        return new self($name, $label ?? $name);
    }

    public function primitive(string $key, string $type, bool $required = false, string|int|float|bool|null $default = null): self
    {
        $this->properties[$key] = new PropertySpecification('prop', new PropertyType($type, false, null, $default), $required, '', '', null);

        return $this;
    }

    /**
     * A union-typed property: the declared type is a list of member type names, which is what makes
     * {@see PropertyType::isPrimitive()} answer false even when every member is primitive.
     *
     * @param list<string> $types
     */
    public function union(string $key, array $types, bool $required = false): self
    {
        $this->properties[$key] = new PropertySpecification('prop', new PropertyType($types, false, null, null), $required, '', '', null);

        return $this;
    }

    public function reference(string $key, string $fqcn, bool $required = false): self
    {
        $this->properties[$key] = new PropertySpecification('prop', new PropertyType($fqcn, false, null, null), $required, '', '', null);

        return $this;
    }

    public function build(): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            $this->name,
            $this->label,
            '',
            null,
            null,
            new CopilotSpecification('', []),
            $this->properties,
            [],
        );
    }
}
