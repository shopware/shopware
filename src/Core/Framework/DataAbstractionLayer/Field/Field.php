<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverInterface;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
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
     * @var FieldResolverInterface|null
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
        $this->flags = $flags;

        return $this;
    }

    public function addFlags(Flag ...$flags): self
    {
        $this->flags = array_merge($this->flags, $flags);

        return $this;
    }

    public function is(string $class): bool
    {
        return $this->getFlag($class) !== null;
    }

    public function getFlag(string $class): ?Flag
    {
        foreach ($this->flags as $flag) {
            if ($flag instanceof $class) {
                return $flag;
            }
        }

        return null;
    }

    /**
     * @return Flag[]
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    public function getSerializer(): FieldSerializerInterface
    {
        $this->initLazy();

        return $this->serializer;
    }

    public function getResolver(): ?FieldResolverInterface
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
