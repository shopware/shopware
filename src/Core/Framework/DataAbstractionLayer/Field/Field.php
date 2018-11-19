<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\FieldExtenderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Filter\FilterRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Write\IdGenerator\GeneratorRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ValueTransformer\ValueTransformerRegistry;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
     * @var ConstraintBuilder|null
     */
    protected $constraintBuilder;

    /**
     * @var FieldExtenderCollection|null
     */
    protected $fieldExtenderCollection;

    /**
     * @var FilterRegistry|null
     */
    protected $filterRegistry;

    /**
     * @var GeneratorRegistry|null
     */
    protected $generatorRegistry;

    /**
     * @var ValidatorInterface|null
     */
    protected $validator;

    /**
     * @var ValueTransformerRegistry|null
     */
    protected $valueTransformerRegistry;

    public function __construct(string $propertyName)
    {
        $this->propertyName = $propertyName;
    }

    public function getExtractPriority(): int
    {
        return 0;
    }

    public function setFlags(Flag  ...$flags): self
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
        foreach ($this->flags as $flag) {
            if ($flag instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function getFlags(): array
    {
        return $this->flags;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
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

    public function setPropertyName(string $propertyName): void
    {
        $this->propertyName = $propertyName;
    }

    public function setConstraintBuilder(ConstraintBuilder $constraintBuilder): void
    {
        $this->constraintBuilder = $constraintBuilder;
    }

    public function setFieldExtenderCollection(FieldExtenderCollection $fieldExtenderCollection): void
    {
        $this->fieldExtenderCollection = $fieldExtenderCollection;
    }

    public function setFilterRegistry(FilterRegistry $filterRegistry): void
    {
        $this->filterRegistry = $filterRegistry;
    }

    public function setGeneratorRegistry(GeneratorRegistry $generatorRegistry): void
    {
        $this->generatorRegistry = $generatorRegistry;
    }

    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function setValueTransformerRegistry(ValueTransformerRegistry $valueTransformerRegistry): void
    {
        $this->valueTransformerRegistry = $valueTransformerRegistry;
    }
}
