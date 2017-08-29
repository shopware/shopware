<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\ApiFilter\FilterRegistry;
use Shopware\Framework\Api2\ApiValueTransformer\ValueTransformerRegistry;
use Shopware\Framework\Api2\Field\Field;
use Shopware\Framework\Api2\Resource\ResourceRegistry;
use Shopware\Framework\Api2\SqlGateway;
use Shopware\Framework\Api2\UuidGenerator\GeneratorRegistry;
use Shopware\Framework\Validation\ConstraintBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DefaultExtender extends FieldExtender
{
    /**
     * @var ResourceRegistry
     */
    private $resourceRegistry;

    /**
     * @var SqlGateway
     */
    private $sqlGateway;

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

    /**
     * @param ResourceRegistry $resourceRegistry
     * @param SqlGateway $sqlGateway
     * @param ValidatorInterface $validator
     * @param ConstraintBuilder $constraintBuilder
     * @param GeneratorRegistry $generatorRegistry
     * @param FilterRegistry $filterRegistry
     * @param ValueTransformerRegistry $valueTransformerRegistry
     */
    public function __construct(
        ResourceRegistry $resourceRegistry,
        SqlGateway $sqlGateway,
        ValidatorInterface $validator,
        ConstraintBuilder $constraintBuilder,
        GeneratorRegistry $generatorRegistry,
        FilterRegistry $filterRegistry,
        ValueTransformerRegistry $valueTransformerRegistry
    ) {
        $this->resourceRegistry = $resourceRegistry;
        $this->sqlGateway = $sqlGateway;
        $this->validator = $validator;
        $this->constraintBuilder = $constraintBuilder;
        $this->generatorRegistry = $generatorRegistry;
        $this->filterRegistry = $filterRegistry;
        $this->valueTransformerRegistry = $valueTransformerRegistry;
    }


    public function extend(Field $field): void
    {
        if ($field instanceof ResourceRegistryAware) {
            $field->setResourceRegistry($this->resourceRegistry);
        }

        if ($field instanceof SqlGatewayAware) {
            $field->setSqlGateway($this->sqlGateway);
        }

        if ($field instanceof  ValidatorAware) {
            $field->setValidator($this->validator);
        }

        if ($field instanceof  ConstraintBuilderAware) {
            $field->setConstraintBuilder($this->constraintBuilder);
        }

        if($field instanceof UuidGeneratorRegistryAware) {
            $field->setUuidGeneratorRegistry($this->generatorRegistry);
        }

        if($field instanceof FilterRegistryAware) {
            $field->setFilterRegistry($this->filterRegistry);
        }

        if($field instanceof ValueTransformerRegistryAware) {
            $field->setValueTransformerRegistry($this->valueTransformerRegistry);
        }
    }
}
