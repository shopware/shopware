<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Registry;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class CompiledElementTypeDefinitionCollection
{
    /**
     * @var array<string, CompiledElementTypeDefinition>
     */
    private array $elements = [];

    public function add(CompiledElementTypeDefinition $definition): void
    {
        $name = $definition->name();

        if (isset($this->elements[$name])) {
            throw ContentSystemException::elementTypeDuplicate($name, $this->elements[$name]->source, $definition->source);
        }

        $this->elements[$name] = $definition;
    }

    /**
     * @return list<CompiledElementTypeDefinition>
     */
    public function all(): array
    {
        return array_values($this->elements);
    }
}
