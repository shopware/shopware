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

namespace Shopware\Api\Write\FieldAware;

use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Write\Filter\FilterRegistry;
use Shopware\Api\Write\UuidGenerator\GeneratorRegistry;
use Shopware\Api\Write\Validation\ConstraintBuilder;
use Shopware\Api\Write\ValueTransformer\ValueTransformerRegistry;
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
        if ($field instanceof  ValidatorAware) {
            $field->setValidator($this->validator);
        }

        if ($field instanceof ConstraintBuilderAware) {
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
