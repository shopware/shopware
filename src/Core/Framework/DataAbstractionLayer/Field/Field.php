<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\AbstractFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\Struct\Struct;

abstract class Field extends Struct
{
    /**
     * @var Flag[]
     */
    protected $flags = [];

    /**
     * @var string
     */
    protected $propertyName;

    /**
     * @var FieldSerializerInterface
     */
    private $serializer;

    /**
     * @var AbstractFieldResolver|null
     */
    private $resolver;

    /**
     * @var FieldAccessorBuilderInterface|null
     */
    private $accessorBuilder;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    public function __construct(string $propertyName)
    {
        $this->propertyName = $propertyName;
        $this->addFlags(new ApiAware(AdminApiSource::class));
    }

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        $this->registry = $registry;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function getExtractPriority(): int
    {
        return 0;
    }

    public function setFlags(Flag ...$flags): self
    {
        $this->flags = [];
        foreach ($flags as $flag) {
            $this->flags[\get_class($flag)] = $flag;
        }
        if (!$this->is(ApiAware::class)) {
            $this->addFlags(new ApiAware(AdminApiSource::class));
        }

        return $this;
    }

    public function addFlags(Flag ...$flags): self
    {
        foreach ($flags as $flag) {
            $this->flags[\get_class($flag)] = $flag;
        }

        return $this;
    }

    public function removeFlag(string $class): self
    {
        unset($this->flags[$class]);

        return $this;
    }

    public function is(string $class): bool
    {
        return $this->getFlag($class) !== null;
    }

    public function getFlag(string $class): ?Flag
    {
        return $this->flags[$class] ?? null;
    }

    /**
     * @return Flag[]
     */
    public function getFlags(): array
    {
        return array_values($this->flags);
    }

    public function getSerializer(): FieldSerializerInterface
    {
        $this->initLazy();

        return $this->serializer;
    }

    /**
     * @return AbstractFieldResolver|null
     */
    public function getResolver()
    {
        $this->initLazy();

        return $this->resolver;
    }

    public function getAccessorBuilder(): ?FieldAccessorBuilderInterface
    {
        $this->initLazy();

        return $this->accessorBuilder;
    }

    public function isCompiled(): bool
    {
        return $this->registry !== null;
    }

    abstract protected function getSerializerClass(): string;

    protected function getResolverClass(): ?string
    {
        return null;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        if ($this instanceof StorageAware) {
            return DefaultFieldAccessorBuilder::class;
        }

        return null;
    }

    private function initLazy(): void
    {
        if ($this->serializer !== null) {
            return;
        }

        $this->serializer = $this->registry->getSerializer($this->getSerializerClass());

        $resolverClass = $this->getResolverClass();
        if ($resolverClass !== null) {
            $this->resolver = $this->registry->getResolver($resolverClass);
        }

        $accessorBuilderClass = $this->getAccessorBuilderClass();
        if ($accessorBuilderClass !== null) {
            $this->accessorBuilder = $this->registry->getAccessorBuilder($accessorBuilderClass);
        }
    }
}
