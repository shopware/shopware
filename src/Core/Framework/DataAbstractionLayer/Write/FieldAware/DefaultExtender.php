<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Filter\FilterRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\IdGenerator\GeneratorRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ValueTransformer\ValueTransformerRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DefaultExtender extends FieldExtender
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var ConstraintBuilder
     */
    private $constraintBuilder;

    /**
     * @var GeneratorRegistry
     */
    private $generatorRegistry;

    /**
     * @var FilterRegistry
     */
    private $filterRegistry;

    /**
     * @var ValueTransformerRegistry
     */
    private $valueTransformerRegistry;

    public function __construct(
        ValidatorInterface $validator,
        ConstraintBuilder $constraintBuilder,
        GeneratorRegistry $generatorRegistry,
        FilterRegistry $filterRegistry,
        ValueTransformerRegistry $valueTransformerRegistry
    ) {
        $this->validator = $validator;
        $this->constraintBuilder = $constraintBuilder;
        $this->generatorRegistry = $generatorRegistry;
        $this->filterRegistry = $filterRegistry;
        $this->valueTransformerRegistry = $valueTransformerRegistry;
    }

    public function extend(Field $field): void
    {
        $field->setValidator($this->validator);
        $field->setConstraintBuilder($this->constraintBuilder);
        $field->setGeneratorRegistry($this->generatorRegistry);
        $field->setFilterRegistry($this->filterRegistry);
        $field->setValueTransformerRegistry($this->valueTransformerRegistry);
    }
}
