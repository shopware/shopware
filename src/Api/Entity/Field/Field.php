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

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Write\Command\WriteCommandQueue;
use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\FieldAware\FieldExtenderCollection;
use Shopware\Api\Entity\Write\FieldException\FieldExceptionStack;
use Shopware\Api\Entity\Write\Filter\FilterRegistry;
use Shopware\Api\Entity\Write\Flag\Flag;
use Shopware\Api\Entity\Write\IdGenerator\GeneratorRegistry;
use Shopware\Api\Entity\Write\Validation\ConstraintBuilder;
use Shopware\Api\Entity\Write\ValueTransformer\ValueTransformerRegistry;
use Shopware\Api\Entity\Write\WriteCommandExtractor;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Framework\Struct\Struct;
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

    abstract public function __invoke(EntityExistence $existence, KeyValuePair $data): \Generator;

    public function getExtractPriority(): int
    {
        return 0;
    }

    /**
     * @param Flag[] ...$flags
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
}
