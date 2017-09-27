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

namespace Shopware\Framework\Write\FieldAware;

use Shopware\Framework\Validation\ConstraintBuilder;
use Shopware\Framework\Write\Field\Field;
use Shopware\Framework\Write\Filter\FilterRegistry;
use Shopware\Framework\Write\ResourceRegistry;
use Shopware\Framework\Write\SqlGateway;
use Shopware\Framework\Write\UuidGenerator\GeneratorRegistry;
use Shopware\Framework\Write\ValueTransformer\ValueTransformerRegistry;
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
     * @param ResourceRegistry         $resourceRegistry
     * @param SqlGateway               $sqlGateway
     * @param ValidatorInterface       $validator
     * @param ConstraintBuilder        $constraintBuilder
     * @param GeneratorRegistry        $generatorRegistry
     * @param FilterRegistry           $filterRegistry
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

        if ($field instanceof UuidGeneratorRegistryAware) {
            $field->setUuidGeneratorRegistry($this->generatorRegistry);
        }

        if ($field instanceof FilterRegistryAware) {
            $field->setFilterRegistry($this->filterRegistry);
        }

        if ($field instanceof ValueTransformerRegistryAware) {
            $field->setValueTransformerRegistry($this->valueTransformerRegistry);
        }
    }
}
