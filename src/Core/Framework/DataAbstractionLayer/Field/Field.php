<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\FieldExtenderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Filter\FilterRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Write\IdGenerator\GeneratorRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ValueTransformer\ValueTransformerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Locale\LocaleLanguageResolverInterface;
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
     * @var string|EntityDefinition|null
     */
    protected $definition;

    /**
     * @var FieldExceptionStack|null
     */
    protected $exceptionStack;

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
     * @var string|null
     */
    protected $path = '';

    /**
     * @var ValidatorInterface|null
     */
    protected $validator;

    /**
     * @var ValueTransformerRegistry|null
     */
    protected $valueTransformerRegistry;

    /**
     * @var WriteContext|null
     */
    protected $writeContext;

    /**
     * @var WriteCommandQueue|null
     */
    protected $commandQueue;

    /**
     * @var WriteCommandExtractor|null
     */
    protected $writeResource;

    /**
     * @var LocaleLanguageResolverInterface|null
     */
    protected $localeLanguageResolver;

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

    public function setDefinition(string $definition): void
    {
        $this->definition = $definition;
    }

    public function setExceptionStack(FieldExceptionStack $exceptionStack): void
    {
        $this->exceptionStack = $exceptionStack;
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

    public function setPath(string $path = ''): void
    {
        $this->path = $path;
    }

    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function setValueTransformerRegistry(ValueTransformerRegistry $valueTransformerRegistry): void
    {
        $this->valueTransformerRegistry = $valueTransformerRegistry;
    }

    public function setWriteContext(WriteContext $writeContext): void
    {
        $this->writeContext = $writeContext;
    }

    public function setCommandQueue(WriteCommandQueue $commandQueue): void
    {
        $this->commandQueue = $commandQueue;
    }

    public function setWriteResource(WriteCommandExtractor $writeResource): void
    {
        $this->writeResource = $writeResource;
    }

    public function getLocaleLanguageResolver(): ?LocaleLanguageResolverInterface
    {
        return $this->localeLanguageResolver;
    }

    public function setLocaleLanguageResolver(LocaleLanguageResolverInterface $localeLanguageResolver): void
    {
        $this->localeLanguageResolver = $localeLanguageResolver;
    }
}
