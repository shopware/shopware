<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Registry;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentElementTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @final
 */
#[Package('framework')]
class ContentElementTypeRegistry implements ResetInterface
{
    /**
     * @var array<string, ContentElementTypeSpecification>
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
     * @param list<ContentElementTypeSpecification> $compiledDefinitions
     * @param iterable<AbstractContentElementTypeLoader> $runtimeLoaders
     */
    public function __construct(
        array $compiledDefinitions,
        private readonly iterable $runtimeLoaders,
    ) {
        foreach ($compiledDefinitions as $definition) {
            $this->register($definition, 'compiled');
        }
    }

    /**
     * @return array<string, ContentElementTypeSpecification>
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

    public function get(string $name): ContentElementTypeSpecification
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

    private function register(ContentElementTypeSpecification $definition, string $source): void
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
