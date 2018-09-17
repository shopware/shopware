<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\FieldAware;

use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Write\Filter\FilterRegistry;
use Shopware\Core\Framework\ORM\Write\IdGenerator\GeneratorRegistry;
use Shopware\Core\Framework\ORM\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\ORM\Write\ValueTransformer\ValueTransformerRegistry;
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
