<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Registry;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentSystemElementTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @final
 */
#[Package('framework')]
class ContentSystemElementTypeRegistry implements ResetInterface
{
    /**
     * @var array<string, ContentSystemElementTypeSpecification>
     */
    private array $types = [];

    /**
     * @var array<string, string>
     */
    private array $sources = [];

    /**
     * @var array<string, true>
     */
    private array $runtimeLoaded = [];

    private bool $runtimeLoadComplete = false;

    /**
     * @internal
     *
     * @param list<CompiledElementTypeDefinition> $compiledDefinitions
     * @param iterable<AbstractContentSystemElementTypeLoader> $runtimeLoaders
     */
    public function __construct(
        array $compiledDefinitions,
        private readonly iterable $runtimeLoaders,
    ) {
        foreach ($compiledDefinitions as $compiled) {
            $this->register($compiled->specification, $compiled->source);
        }
    }

    /**
     * @return array<string, ContentSystemElementTypeSpecification>
     */
    public function all(): array
    {
        $this->ensureLoaded();

        return $this->types;
    }

    public function has(string $name): bool
    {
        $this->ensureLoaded();

        return isset($this->types[$name]);
    }

    public function get(string $name): ContentSystemElementTypeSpecification
    {
        $this->ensureLoaded();

        if (!isset($this->types[$name])) {
            throw ContentSystemException::elementTypeNotFound($name);
        }

        return $this->types[$name];
    }

    public function reset(): void
    {
        foreach ($this->runtimeLoaded as $name => $_) {
            unset($this->types[$name], $this->sources[$name]);
        }

        $this->runtimeLoaded = [];
        $this->runtimeLoadComplete = false;
    }

    private function register(ContentSystemElementTypeSpecification $definition, string $source): void
    {
        $name = $definition->name();

        if (isset($this->types[$name])) {
            throw ContentSystemException::elementTypeDuplicate($name, $this->sources[$name], $source);
        }

        $this->types[$name] = $definition;
        $this->sources[$name] = $source;
    }

    private function ensureLoaded(): void
    {
        if ($this->runtimeLoadComplete) {
            return;
        }

        $this->runtimeLoadComplete = true;

        foreach ($this->runtimeLoaders as $loader) {
            $source = $loader::class;

            foreach ($loader->load() as $definition) {
                $this->register($definition, $source);
                $this->runtimeLoaded[$definition->name()] = true;
            }
        }
    }
}
