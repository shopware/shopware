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
     * A property whose declared type is passed through verbatim, in either shape the YAML admits: a single
     * type name (a primitive, `object`, or an FQCN) or a list of them for a union. For a test that varies the
     * declaration itself rather than picking one of the named kinds above.
     *
     * @param string|list<string> $type
     */
    public function declared(string $key, string|array $type, bool $required = false): self
    {
        $this->properties[$key] = new PropertySpecification('prop', new PropertyType($type, false, null, null), $required, '', '', null);

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
