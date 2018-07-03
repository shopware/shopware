<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldAware\FieldExtenderCollection;
use Shopware\Core\Framework\ORM\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
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
     * @var ConstraintBuilder
     */
    protected $constraintBuilder;

    /**
     * @var string|EntityDefinition
     */
    protected $definition;

    /**
     * @var FieldExceptionStack
     */
    protected $exceptionStack;

    /**
     * @var FieldExtenderCollection
     */
    protected $fieldExtenderCollection;

    /**
     * @var FilterRegistry
     */
    protected $filterRegistry;

    /**
     * @var GeneratorRegistry
     */
    protected $generatorRegistry;

    /**
     * @var string
     */
    protected $path = '';

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var ValueTransformerRegistry
     */
    protected $valueTransformerRegistry;

    /**
     * @var WriteContext
     */
    protected $writeContext;

    /**
     * @var WriteCommandQueue
     */
    protected $commandQueue;

    /**
     * @var WriteCommandExtractor
     */
    protected $writeResource;

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

    abstract public function invoke(EntityExistence $existence, KeyValuePair $data): \Generator;

    public function getExtractPriority(): int
    {
        return 0;
    }

    /**
     * @param Flag ...$flags
     *
     * @return self
     */
    public function setFlags(Flag  ...$flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
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

    /**
     * @param $value
     * @param $key
     */
    private function validateContextHasPermission($value, $key): void
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

        throw new InvalidFieldException($this->path . '/' . $key, $violationList);
    }

    /**
     * @param string $flag
     *
     * @return bool
     */
    private function contextHasPermission($flag)
    {
        /** @var ArrayStruct $extension */
        $extension = $this->writeContext->getContext()->getExtension('write_protection');

        if ($extension !== null && $extension->get($flag) !== null) {
            return true;
        }

        return false;
    }
}
