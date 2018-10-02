<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldAware\FieldExtenderCollection;
use Shopware\Core\Framework\ORM\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\ORM\Write\FieldException\InsufficientWritePermissionException;
use Shopware\Core\Framework\ORM\Write\Filter\FilterRegistry;
use Shopware\Core\Framework\ORM\Write\Flag\Flag;
use Shopware\Core\Framework\ORM\Write\Flag\WriteProtected;
use Shopware\Core\Framework\ORM\Write\IdGenerator\GeneratorRegistry;
use Shopware\Core\Framework\ORM\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\ORM\Write\ValueTransformer\ValueTransformerRegistry;
use Shopware\Core\Framework\ORM\Write\WriteCommandExtractor;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Locale\LocaleLanguageResolverInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
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

    public function __invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $value = $data->getValue();
        $key = $data->getKey();

        if ($this->is(WriteProtected::class)) {
            $this->validateContextHasPermission($value, $key);
        }

        yield from $this->invoke($existence, $data);
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

    abstract protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator;

    /**
     * @param mixed  $value
     * @param string $key
     */
    private function validateContextHasPermission($value, string $key): void
    {
        /** @var WriteProtected $flag */
        $flag = $this->getFlag(WriteProtected::class);

        if ($this->contextHasPermission($flag->getPermissionKey())) {
            return;
        }

        $violationList = new ConstraintViolationList();
        $violationList->add(
            new ConstraintViolation(
                'This field is write-protected.',
                'This field is write-protected.',
                [],
                $value,
                $key,
                $value
            )
        );

        throw new InsufficientWritePermissionException($this->path . '/' . $key, $violationList);
    }

    private function contextHasPermission(string $flag): bool
    {
        /** @var ArrayStruct $extension */
        $extension = $this->writeContext->getContext()->getExtension('write_protection');

        if ($extension !== null && $extension->get($flag) !== null) {
            return true;
        }

        return false;
    }
}
